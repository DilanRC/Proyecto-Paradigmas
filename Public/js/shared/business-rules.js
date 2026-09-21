export const BUSINESS_CAPABILITIES = Object.freeze({
    COMPRADOR: Object.freeze({
        id: 'COMPRADOR',
        label: 'Explorar como comprador',
        description: 'Explorar y guardar oportunidades de ganado.',
        requiredSections: [],
    }),
    PRODUCTOR: Object.freeze({
        id: 'PRODUCTOR',
        label: 'Vender o publicar ganado',
        description: 'Publicar ganado y administrar una o más fincas.',
        requiredSections: ['fincas'],
    }),
    TRANSPORTISTA: Object.freeze({
        id: 'TRANSPORTISTA',
        label: 'Ofrecer fletes',
        description: 'Ofrecer servicios de transporte y asociar vehículos después del registro.',
        requiredSections: [],
    }),
});

export const REGISTRATION_RULES = Object.freeze({
    persona: Object.freeze({
        required: ['identificacionTipo', 'identificacionNumero', 'nombres', 'apellidos', 'telefono', 'correoElectronico', 'password'],
        aliasOptional: true,
        phoneDigitsMin: 8,
        phoneDigitsMax: 15,
        passwordMinLength: 8,
    }),
    productor: Object.freeze({
        requiresAtLeastOneFinca: true,
        fincaNameRequired: true,
        addressOptionalAtRegistration: true,
        multipleFincasAllowed: true,
    }),
    comprador: Object.freeze({
        extraFieldsAtRegistration: [],
    }),
    transportista: Object.freeze({
        vehicleRequiredAtRegistration: false,
        vehicleCanBeAssignedLater: true,
    }),
});

export function normalizeCapabilities(values = []) {
    return [...new Set(values)]
        .map((value) => String(value).toUpperCase())
        .filter((value) => Object.hasOwn(BUSINESS_CAPABILITIES, value));
}

export function requiredRegistrationSteps(capabilities = []) {
    const selected = normalizeCapabilities(capabilities);
    const steps = ['persona', 'intereses'];
    if (selected.includes('PRODUCTOR')) steps.push('fincas');
    steps.push('revision');
    return steps;
}

export function validatePersonaDraft(persona = {}, { requirePassword = true } = {}) {
    const errors = {};
    const required = requirePassword
        ? REGISTRATION_RULES.persona.required
        : REGISTRATION_RULES.persona.required.filter((field) => !['password'].includes(field));
    for (const field of required) {
        if (!String(persona[field] ?? '').trim()) errors[field] = 'Este dato es obligatorio.';
    }

    if (persona.nombres && String(persona.nombres).trim().length < 2) errors.nombres = 'Ingrese al menos 2 caracteres.';
    if (persona.apellidos && String(persona.apellidos).trim().length < 2) errors.apellidos = 'Ingrese al menos 2 caracteres.';
    for (const field of ['nombres', 'apellidos']) {
        const value = String(persona[field] ?? '').trim();
        if (value && !/^[\p{L}\p{M}][\p{L}\p{M} .\u0027’\-]*$/u.test(value)) {
            errors[field] = 'Use únicamente letras, espacios, puntos, apóstrofes o guiones.';
        }
        if (value.length > 75) errors[field] = 'No puede superar 75 caracteres.';
    }

    const phoneDigits = String(persona.telefono ?? '').replace(/\D/g, '');
    if (persona.telefono && (phoneDigits.length < 8 || phoneDigits.length > 15)) {
        errors.telefono = 'Use entre 8 y 15 dígitos.';
    }

    if (persona.correoElectronico && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(persona.correoElectronico))) {
        errors.correoElectronico = 'Ingrese un correo válido.';
    }

    if (persona.password && String(persona.password).length < REGISTRATION_RULES.persona.passwordMinLength) {
        errors.password = 'Use al menos 8 caracteres.';
    }

    if (requirePassword && persona.password !== persona.passwordConfirmacion) {
        errors.passwordConfirmacion = 'Las contraseñas no coinciden.';
    }

    return errors;
}

export function validateCapabilities(capabilities = []) {
    return normalizeCapabilities(capabilities).length > 0
        ? {}
        : { capacidades: 'Seleccione al menos una forma en la que desea usar TinderCows.' };
}

export function validateFincas(fincas = [], capabilities = []) {
    if (!normalizeCapabilities(capabilities).includes('PRODUCTOR')) return {};
    const clean = Array.isArray(fincas) ? fincas : [];
    if (clean.length === 0) return { fincas: 'Para vender o publicar debe registrar al menos una finca.' };
    const names = clean.map((finca) => String(finca?.nombre ?? '').trim()).filter(Boolean);
    if (names.length !== clean.length) return { fincas: 'Cada finca necesita un nombre.' };
    const normalized = names.map((name) => name.toLocaleLowerCase('es'));
    if (new Set(normalized).size !== normalized.length) {
        return { fincas: 'No repita la misma finca dentro de este registro.' };
    }
    return {};
}

export function buildRegistrationSummary(draft) {
    const capabilities = normalizeCapabilities(draft.capacidades);
    const nombreLegado = String(draft.persona?.nombre ?? '').trim();
    const nombres = String(draft.persona?.nombres ?? '').trim()
        || (nombreLegado ? nombreLegado.split(/\s+/).slice(0, -1).join(' ') : '');
    const apellidos = String(draft.persona?.apellidos ?? '').trim()
        || (nombreLegado ? nombreLegado.split(/\s+/).slice(-1).join(' ') : '');
    return {
        persona: {
            identificacionTipo: draft.persona?.identificacionTipo ?? '',
            identificacionNumero: String(draft.persona?.identificacionNumero ?? '').trim(),
            nombres,
            apellidos,
            nombre: [nombres, apellidos].filter(Boolean).join(' '),
            alias: String(draft.persona?.alias ?? '').trim(),
            telefono: String(draft.persona?.telefono ?? '').trim(),
            correoElectronico: String(draft.persona?.correoElectronico ?? '').trim(),
        },
        capacidades: capabilities,
        fincas: capabilities.includes('PRODUCTOR') ? (draft.fincas ?? []) : [],
        reglasAplicadas: {
            identidadUnica: true,
            capacidadesNoSonRolesAdministrativos: true,
            compradorSinDatosDuplicados: true,
            productorPuedeTenerVariasFincas: true,
            transportistaPuedeAsignarVehiculoDespues: true,
            desactivacionNoImplicaBorrado: true,
        },
    };
}
