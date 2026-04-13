import React from 'react';

export default function SuperiaLogo({ size = 'md', showText = true }) {
    const sizes = {
        sm: { svg: 24, text: 'text-lg' },
        md: { svg: 32, text: 'text-2xl' },
        lg: { svg: 36, text: 'text-2xl' },
    };
    const s = sizes[size] || sizes.md;

    return (
        <div className="flex items-center gap-2">
            <svg className="shrink-0" fill="none" height={s.svg} viewBox="0 0 40 40" width={s.svg} xmlns="http://www.w3.org/2000/svg">
                <path d="M20 10C20 10 21 6 25 4C25 4 24 8 20 10Z" fill="#10B981" />
                <path d="M20 10C17 10 16 8 16 8C16 8 18.5 6 20 6" stroke="#10B981" strokeLinecap="round" strokeWidth="1.5" />
                <path d="M28 16C28 12.6863 25.3137 10 22 10H18C14.6863 10 12 12.6863 12 16C12 19.3137 14.6863 22 18 22H22C25.3137 22 28 24.6863 28 28C28 31.3137 25.3137 34 22 34H18C14.6863 34 12 31.3137 12 28" stroke="#003E54" strokeLinecap="round" strokeWidth="4" />
            </svg>
            {showText && (
                <span className={`font-bold tracking-tighter ${s.text}`} style={{ color: '#003E54' }}>
                    Superia
                </span>
            )}
        </div>
    );
}
