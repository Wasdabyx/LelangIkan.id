const API_URL_FALLBACK = 'http://localhost:8000/api';

export const API_URL = process.env.EXPO_PUBLIC_API_URL || API_URL_FALLBACK;
export const BATAS_WAKTU = 15000;
