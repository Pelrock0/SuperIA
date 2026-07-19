const INVARIABLES = new Set([
    'lunes',
    'martes',
    'miercoles',
    'jueves',
    'viernes',
    'crisis',
    'tesis',
    'atlas',
    'analisis',
    'sintesis',
    'dosis',
    'hipotesis',
    'virus',
    'oasis',
    'paraguas',
    'cumpleaños',
    'abrelatas',
    'sacacorchos',
]);

const ACCENT_MAP = {
    'á': 'a', 'à': 'a', 'ä': 'a', 'â': 'a',
    'é': 'e', 'è': 'e', 'ë': 'e', 'ê': 'e',
    'í': 'i', 'ì': 'i', 'ï': 'i', 'î': 'i',
    'ó': 'o', 'ò': 'o', 'ö': 'o', 'ô': 'o',
    'ú': 'u', 'ù': 'u', 'ü': 'u', 'û': 'u',
    'ç': 'c',
};

const PLURAL_STEM_FINAL_CONSONANTS = new Set(['n', 'r', 'l', 'd', 'j', 'z', 'x']);

function stripAccents(s) {
    let out = '';
    for (const ch of s) {
        out += ACCENT_MAP[ch] ?? ch;
    }
    return out;
}

function normalizeToken(s) {
    if (INVARIABLES.has(s)) return s;

    const len = s.length;

    if (len >= 5 && s.slice(-3) === 'ces') {
        return s.slice(0, -3) + 'z';
    }

    if (len >= 4 && s.slice(-1) === 's') {
        const stripS = s.slice(0, -1);
        const stripEs = s.slice(-2) === 'es' ? s.slice(0, -2) : null;

        if (stripEs !== null && PLURAL_STEM_FINAL_CONSONANTS.has(stripEs.slice(-1))) {
            return stripEs;
        }
        return stripS;
    }

    return s;
}

export default function normalize(name) {
    if (typeof name !== 'string') return '';

    const trimmed = name.trim().replace(/\s+/g, ' ');
    if (trimmed === '') return '';

    const lowered = trimmed.toLocaleLowerCase('es');
    const noAccents = stripAccents(lowered);

    return noAccents.split(' ').map(normalizeToken).join(' ');
}
