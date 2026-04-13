/**
 * Ratcliff/Obershelp similarity — equivalent to PHP's similar_text() percentage.
 * Returns a float 0.0–1.0.
 */
export default function similarText(a, b) {
    const s1 = a.toLowerCase().trim();
    const s2 = b.toLowerCase().trim();
    if (s1 === s2) return 1.0;
    if (s1.length === 0 || s2.length === 0) return 0.0;

    function lcs(str1, str2) {
        let longest = 0, start1 = 0, start2 = 0;
        for (let i = 0; i < str1.length; i++) {
            for (let j = 0; j < str2.length; j++) {
                let k = 0;
                while (i + k < str1.length && j + k < str2.length && str1[i + k] === str2[j + k]) k++;
                if (k > longest) { longest = k; start1 = i; start2 = j; }
            }
        }
        return { longest, start1, start2 };
    }

    function recurse(str1, str2) {
        const { longest, start1, start2 } = lcs(str1, str2);
        if (longest === 0) return 0;
        let count = longest;
        if (start1 > 0 && start2 > 0) count += recurse(str1.substring(0, start1), str2.substring(0, start2));
        const end1 = start1 + longest, end2 = start2 + longest;
        if (end1 < str1.length && end2 < str2.length) count += recurse(str1.substring(end1), str2.substring(end2));
        return count;
    }

    const matching = recurse(s1, s2);
    return (2 * matching) / (s1.length + s2.length);
}
