// Страницы «Применение по показаниям» и методы по нозологиям (глава 7 Рекомендаций 2026).
// Тексты берутся из src/data/metody.json (бывший data_nozologii.py) и src/data/metody-obshchee.json; здесь только типы и общие константы.
import data from '../data/metody.json';
import obsh from '../data/metody-obshchee.json';

export const REC = 'documents/clinical-recommendations-2026.pdf';
export const REC_MB = '12,6'; // размер PDF Рекомендаций, МБ (13 164 418 байт)
export const INSTR = 'documents/instrukciya-skinolaks-2026.pdf';
export const CATALOG = 'specialistam-metody.html';
export const NB = ' ';
export const PHASES = ['1', '1–2', '2', '2–3', '3'];

export interface Nozologiya {
  slug: string; group: string; sec: string; page: number; title: string; med: string; sub?: string; lead: string;
  cond?: string[]; phases?: string[]; freq?: string[]; freq_note?: string; freq_text?: [string, string][]; special?: string[];
  contra?: string[]; warn?: string; warn_sec?: string; decub?: boolean; vac?: boolean; devices?: boolean;
  method?: string; instr: [string, string]; editorial?: string;
}
export type Indication = [key: string, title: string, quotes: string[], slugs: string[], note: string | null];

export const N = data.nozologii as unknown as Nozologiya[];
export const BY: Record<string, Nozologiya> = Object.fromEntries(N.map((d) => [d.slug, d]));
export const INDICATIONS = data.indications as unknown as Indication[];
export const VAC = data.vac as unknown as [string, number, string, string, string[], string[] | null][];
export const DEVICES = data.devices as unknown as [string, number, string, string, string][];
export const DECUB_TABLE = data.decubTable as unknown as [string, string[]][];
export const METHODS = data.methods as Record<string, { file: string; name: string }>;
export const { contraAbs: GENERAL_CONTRA_ABS, contraRel: GENERAL_CONTRA_REL, instrContra: INSTR_CONTRA, immediate: IMMEDIATE } = obsh;
export const NONINF = obsh.noninf as [string, string, string][];

// показание Инструкции, в которое входит нозология: slug → [ключ, название]
export const IND_OF: Record<string, [string, string]> = Object.fromEntries(
  INDICATIONS.flatMap(([k, t, , ss]) => ss.map((s) => [s, [k, t] as [string, string]])),
);
