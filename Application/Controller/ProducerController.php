<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Producer;
use PDOException;

final class ProducerController
{
    private const IDENTIFICATION_TYPES = ['NATIONAL_ID', 'LEGAL_ID', 'DIMEX', 'NITE'];
    private const STATUSES = ['ACTIVE', 'INACTIVE'];

    public function __construct(private readonly Producer $model)
    {
    }

    public function process(string $method, array $query, array $body): array
    {
        try {
            return match ($method) {
                'GET' => $this->get($query),
                'POST' => $this->create($body),
                'PUT' => $this->update($body),
                'DELETE' => $this->deactivate($body),
                default => $this->response(false, 'Method not allowed.', null, 405),
            };
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return $this->response(false, 'The identification number or email is already registered.', null, 409);
            }
            throw $exception;
        }
    }

    private function get(array $query): array
    {
        $id = filter_var($query['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (array_key_exists('id', $query)) {
            if ($id === false || $id === null) {
                return $this->response(false, 'The producer identifier is invalid.', null, 422);
            }
            $producer = $this->model->findById((int) $id);
            return $producer === null
                ? $this->response(false, 'Producer not found.', null, 404)
                : $this->response(true, 'Producer retrieved successfully.', $producer);
        }

        $search = $this->sanitizeText($query['q'] ?? '', 150);
        $status = strtoupper($this->sanitizeText($query['status'] ?? 'ALL', 10));
        if (!in_array($status, [...self::STATUSES, 'ALL'], true)) {
            return $this->response(false, 'The status filter is invalid.', null, 422);
        }
        $producers = $this->model->list($search, $status);

        return $this->response(true, 'Producers retrieved successfully.', ['producers' => $producers, 'total' => count($producers)]);
    }

    private function create(array $body): array
    {
        [$data, $errors] = $this->validate($body);
        if ($errors !== []) {
            return $this->response(false, 'Review the highlighted fields.', null, 422, $errors);
        }
        $duplicates = $this->validateDuplicates($data);
        if ($duplicates !== []) {
            return $this->response(false, 'A producer with these details already exists.', null, 409, $duplicates);
        }
        return $this->response(true, 'Producer created successfully.', $this->model->create($data), 201);
    }

    private function update(array $body): array
    {
        $id = filter_var($body['producer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || $id === null) {
            return $this->response(false, 'The producer identifier is invalid.', null, 422);
        }
        if ($this->model->findById((int) $id) === null) {
            return $this->response(false, 'Producer not found.', null, 404);
        }
        [$data, $errors] = $this->validate($body);
        if ($errors !== []) {
            return $this->response(false, 'Review the highlighted fields.', null, 422, $errors);
        }
        $duplicates = $this->validateDuplicates($data, (int) $id);
        if ($duplicates !== []) {
            return $this->response(false, 'A producer with these details already exists.', null, 409, $duplicates);
        }
        return $this->response(true, 'Producer updated successfully.', $this->model->update((int) $id, $data));
    }

    private function deactivate(array $body): array
    {
        $id = filter_var($body['producer_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || $id === null) {
            return $this->response(false, 'The producer identifier is invalid.', null, 422);
        }
        $producer = $this->model->findById((int) $id);
        if ($producer === null) {
            return $this->response(false, 'Producer not found.', null, 404);
        }
        if ($producer['status'] === 'INACTIVE') {
            return $this->response(true, 'The producer is already inactive.', $producer);
        }
        $this->model->deactivate((int) $id);
        return $this->response(true, 'Producer deactivated successfully.', $this->model->findById((int) $id));
    }

    private function validate(array $input): array
    {
        $data = [
            'identification_type' => strtoupper($this->sanitizeText($input['identification_type'] ?? '', 20)),
            'identification_number' => preg_replace('/\D+/', '', (string) ($input['identification_number'] ?? '')) ?? '',
            'name' => $this->sanitizeText($input['name'] ?? '', 150),
            'farm_name' => $this->sanitizeText($input['farm_name'] ?? '', 150),
            'phone' => preg_replace('/[^0-9+]/', '', (string) ($input['phone'] ?? '')) ?? '',
            'email' => mb_strtolower($this->sanitizeText($input['email'] ?? '', 150), 'UTF-8'),
            'address' => $this->sanitizeText($input['address'] ?? '', 255),
            'status' => strtoupper($this->sanitizeText($input['status'] ?? 'ACTIVE', 10)),
        ];
        $errors = [];
        if (!in_array($data['identification_type'], self::IDENTIFICATION_TYPES, true)) $errors['identification_type'] = 'Select a valid identification type.';
        $identificationLength = strlen($data['identification_number']);
        if ($identificationLength < 5 || $identificationLength > 20) $errors['identification_number'] = 'The identification number must contain 5 to 20 digits.';
        if (mb_strlen($data['name'], 'UTF-8') < 3) $errors['name'] = 'The name must contain at least 3 characters.';
        if ($data['phone'] === '' || !preg_match('/^\+?[0-9]{8,15}$/', $data['phone'])) $errors['phone'] = 'Enter a valid phone number with 8 to 15 digits.';
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
        if (mb_strlen($data['address'], 'UTF-8') < 5) $errors['address'] = 'The address must contain at least 5 characters.';
        if (!in_array($data['status'], self::STATUSES, true)) $errors['status'] = 'Select a valid status.';
        return [$data, $errors];
    }

    private function validateDuplicates(array $data, ?int $excludedId = null): array
    {
        $errors = [];
        if ($this->model->identificationExists($data['identification_number'], $excludedId)) $errors['identification_number'] = 'This identification number is already registered.';
        if ($this->model->emailExists($data['email'], $excludedId)) $errors['email'] = 'This email address is already registered.';
        return $errors;
    }

    private function sanitizeText(mixed $value, int $maximumLength): string
    {
        if (!is_scalar($value)) return '';
        $text = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
        return mb_substr($text, 0, $maximumLength, 'UTF-8');
    }

    private function response(bool $success, string $message, mixed $data = null, int $httpStatus = 200, array $errors = []): array
    {
        $body = ['success' => $success, 'message' => $message, 'data' => $data];
        if ($errors !== []) $body['errors'] = $errors;
        return ['status' => $httpStatus, 'body' => $body];
    }
}
