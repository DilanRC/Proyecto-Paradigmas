<?php

declare(strict_types=1);

namespace Application\Model;

use PDO;

final class Producer
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function list(string $search = '', string $status = 'ALL'): array
    {
        $conditions = [];
        $parameters = [];

        if ($search !== '') {
            $conditions[] = '(identification_number LIKE :identificationNumber OR name LIKE :name OR farm_name LIKE :farmName OR email LIKE :email)';
            $term = '%' . $search . '%';
            $parameters = ['identificationNumber' => $term, 'name' => $term, 'farmName' => $term, 'email' => $term];
        }

        if ($status !== 'ALL') {
            $conditions[] = 'status = :status';
            $parameters['status'] = $status;
        }

        $sql = 'SELECT producer_id, identification_type, identification_number, name, farm_name, phone, email, address, status, created_at, updated_at FROM producers';
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= " ORDER BY CASE WHEN status = 'ACTIVE' THEN 0 ELSE 1 END, name ASC";

        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = $this->connection->prepare('SELECT producer_id, identification_type, identification_number, name, farm_name, phone, email, address, status, created_at, updated_at FROM producers WHERE producer_id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $producer = $statement->fetch();

        return $producer === false ? null : $producer;
    }

    public function identificationExists(string $identification, ?int $excludedId = null): bool
    {
        return $this->uniqueValueExists('identification_number', $identification, $excludedId);
    }

    public function emailExists(string $email, ?int $excludedId = null): bool
    {
        return $this->uniqueValueExists('email', $email, $excludedId);
    }

    public function create(array $data): array
    {
        $statement = $this->connection->prepare('INSERT INTO producers (identification_type, identification_number, name, farm_name, phone, email, address, status) VALUES (:identification_type, :identification_number, :name, :farm_name, :phone, :email, :address, :status)');
        $statement->execute($this->saveParameters($data));

        return $this->findById((int) $this->connection->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $statement = $this->connection->prepare('UPDATE producers SET identification_type = :identification_type, identification_number = :identification_number, name = :name, farm_name = :farm_name, phone = :phone, email = :email, address = :address, status = :status WHERE producer_id = :id');
        $parameters = $this->saveParameters($data);
        $parameters['id'] = $id;
        $statement->execute($parameters);

        return $this->findById($id);
    }

    public function deactivate(int $id): bool
    {
        $statement = $this->connection->prepare("UPDATE producers SET status = 'INACTIVE' WHERE producer_id = :id");
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    private function uniqueValueExists(string $column, string $value, ?int $excludedId): bool
    {
        $sql = sprintf('SELECT 1 FROM producers WHERE %s = :value', $column);
        $parameters = ['value' => $value];
        if ($excludedId !== null) {
            $sql .= ' AND producer_id <> :id';
            $parameters['id'] = $excludedId;
        }
        $statement = $this->connection->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    private function saveParameters(array $data): array
    {
        return [
            'identification_type' => $data['identification_type'],
            'identification_number' => $data['identification_number'],
            'name' => $data['name'],
            'farm_name' => $data['farm_name'] !== '' ? $data['farm_name'] : null,
            'phone' => $data['phone'],
            'email' => $data['email'],
            'address' => $data['address'],
            'status' => $data['status'],
        ];
    }
}
