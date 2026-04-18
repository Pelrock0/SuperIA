import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

import esLogin from './locales/es/login.json';
import enLogin from './locales/en/login.json';
import esCommon from './locales/es/common.json';
import enCommon from './locales/en/common.json';

i18n.use(LanguageDetector)
    .use(initReactI18next)
    .init({
        fallbackLng: 'es',
        supportedLngs: ['es', 'en'],
        ns: ['common', 'login'],
        defaultNS: 'common',
        interpolation: { escapeValue: false },
        detection: {
            order: ['cookie', 'navigator'],
            caches: ['cookie'],
            lookupCookie: 'lang',
        },
        resources: {
            es: { login: esLogin, common: esCommon },
            en: { login: enLogin, common: enCommon },
        },
    });

export default i18n;
