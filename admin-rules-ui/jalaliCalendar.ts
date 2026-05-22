/** Gregorian ↔ Jalali conversion (jalaali-js algorithm). Storage stays ISO Gregorian YYYY-MM-DD. */

export interface JalaliYmd {
  jy: number;
  jm: number;
  jd: number;
}

export interface GregorianYmd {
  gy: number;
  gm: number;
  gd: number;
}

export const JALALI_MONTH_NAMES_FA = [
  'فروردین',
  'اردیبهشت',
  'خرداد',
  'تیر',
  'مرداد',
  'شهریور',
  'مهر',
  'آبان',
  'آذر',
  'دی',
  'بهمن',
  'اسفند',
] as const;

const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'] as const;

export function toPersianDigits(value: string | number): string {
  return String(value).replace(/\d/g, (d) => PERSIAN_DIGITS[parseInt(d, 10)] ?? d);
}

export function isLeapJalaliYear(jy: number): boolean {
  const r = jy % 33;
  return r === 1 || r === 5 || r === 9 || r === 13 || r === 17 || r === 22 || r === 26 || r === 30;
}

export function jalaliMonthLength(jy: number, jm: number): number {
  if (jm < 1 || jm > 12) return 0;
  if (jm <= 6) return 31;
  if (jm <= 11) return 30;
  return isLeapJalaliYear(jy) ? 30 : 29;
}

export function toJalaali(gy: number, gm: number, gd: number): JalaliYmd {
  const gy2 = gm > 2 ? gy + 1 : gy;
  let days =
    355666 +
    365 * gy +
    Math.floor((gy2 + 3) / 4) -
    Math.floor((gy2 + 99) / 100) +
    Math.floor((gy2 + 399) / 400) +
    gd +
    [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334][gm - 1];
  let jy = -1595 + 33 * Math.floor(days / 12053);
  days %= 12053;
  jy += 4 * Math.floor(days / 1461);
  days %= 1461;
  if (days > 365) {
    jy += Math.floor((days - 1) / 365);
    days = (days - 1) % 365;
  }
  let jm: number;
  let jd: number;
  if (days < 186) {
    jm = 1 + Math.floor(days / 31);
    jd = 1 + (days % 31);
  } else {
    jm = 7 + Math.floor((days - 186) / 30);
    jd = 1 + ((days - 186) % 30);
  }
  return { jy, jm, jd };
}

export function toGregorian(jy: number, jm: number, jd: number): GregorianYmd {
  let jy2 = jy + 1595;
  let days =
    -355668 +
    365 * jy2 +
    Math.floor(jy2 / 33) * 8 +
    Math.floor(((jy2 % 33) + 3) / 4) +
    jd +
    (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
  let gy = 400 * Math.floor(days / 146097);
  days %= 146097;
  let leap = true;
  if (days >= 36525) {
    days--;
    gy += 100 * Math.floor(days / 36524);
    days %= 36524;
    if (days >= 365) days++;
    else leap = false;
  }
  gy += 4 * Math.floor(days / 1461);
  days %= 1461;
  if (days >= 366) {
    leap = false;
    days--;
    gy += Math.floor(days / 365);
    days = days % 365;
  }
  const sal_a = [
    0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31,
  ];
  let gm = 0;
  for (gm = 0; gm < 13 && days >= sal_a[gm]; gm++) {
    days -= sal_a[gm];
  }
  return { gy, gm, gd: days + 1 };
}

export function parseGregorianIso(iso: string): GregorianYmd | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso.trim());
  if (!m) return null;
  const gy = parseInt(m[1], 10);
  const gm = parseInt(m[2], 10);
  const gd = parseInt(m[3], 10);
  if (gm < 1 || gm > 12 || gd < 1 || gd > 31) return null;
  return { gy, gm, gd };
}

export function gregorianIsoFromYmd({ gy, gm, gd }: GregorianYmd): string {
  return `${String(gy)}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`;
}

export function jalaliFromGregorianIso(iso: string, fallback = new Date()): JalaliYmd {
  const parsed = parseGregorianIso(iso);
  if (!parsed) {
    const d = fallback;
    return toJalaali(d.getFullYear(), d.getMonth() + 1, d.getDate());
  }
  return toJalaali(parsed.gy, parsed.gm, parsed.gd);
}

export function gregorianIsoFromJalali(jy: number, jm: number, jd: number): string {
  const g = toGregorian(jy, jm, jd);
  return gregorianIsoFromYmd(g);
}

export function compareGregorianIso(a: string, b: string): number {
  return a.localeCompare(b);
}

export function clampGregorianIsoToMin(iso: string, minIso: string): string {
  return compareGregorianIso(iso, minIso) < 0 ? minIso : iso;
}

export function formatJalaliLong(
  isoGregorian: string,
  monthNames: readonly string[] = JALALI_MONTH_NAMES_FA,
  persianDigits = true,
): string {
  const { jy, jm, jd } = jalaliFromGregorianIso(isoGregorian);
  const month = monthNames[jm - 1] ?? String(jm);
  const dayStr = persianDigits ? toPersianDigits(jd) : String(jd);
  const yearStr = persianDigits ? toPersianDigits(jy) : String(jy);
  return `${dayStr} ${month} ${yearStr}`;
}

export function todayGregorianIso(ref = new Date()): string {
  return gregorianIsoFromYmd({
    gy: ref.getFullYear(),
    gm: ref.getMonth() + 1,
    gd: ref.getDate(),
  });
}

export function jalaliYearRange(minIso: string, maxYearsAhead = 2, ref = new Date()): number[] {
  const minJ = jalaliFromGregorianIso(minIso, ref);
  const maxJ = toJalaali(ref.getFullYear() + maxYearsAhead, 12, 29);
  const out: number[] = [];
  for (let y = minJ.jy; y <= maxJ.jy; y++) {
    out.push(y);
  }
  return out.length ? out : [minJ.jy];
}
