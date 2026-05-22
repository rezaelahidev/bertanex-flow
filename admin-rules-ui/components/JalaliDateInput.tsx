import {
  clampGregorianIsoToMin,
  compareGregorianIso,
  formatJalaliLong,
  gregorianIsoFromJalali,
  jalaliFromGregorianIso,
  jalaliMonthLength,
  jalaliYearRange,
  toPersianDigits,
  type JalaliYmd,
} from '../jalaliCalendar';
import { t, useCfg } from './ConfigContext';
import type { GroRulesEditorConfig } from '../ruleModel';
import React, { useCallback, useMemo } from 'react';

export interface JalaliDateInputProps {
  value: string;
  min: string;
  onChange: (isoGregorian: string) => void;
  className?: string;
}

function monthNamesFor(cfg: GroRulesEditorConfig): readonly string[] {
  const fromCfg = cfg.jalaliMonthNames;
  if (Array.isArray(fromCfg) && fromCfg.length === 12) {
    return fromCfg;
  }
  return [
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
  ];
}

function clampJalaliToMin(j: JalaliYmd, minIso: string): JalaliYmd {
  const iso = gregorianIsoFromJalali(j.jy, j.jm, j.jd);
  const clamped = clampGregorianIsoToMin(iso, minIso);
  return jalaliFromGregorianIso(clamped);
}

export function JalaliDateInput({ value, min, onChange, className = '' }: JalaliDateInputProps): React.ReactElement {
  const cfg = useCfg();
  const months = monthNamesFor(cfg);
  const safeValue = useMemo(() => clampGregorianIsoToMin(value || min, min), [value, min]);
  const selected = useMemo(() => clampJalaliToMin(jalaliFromGregorianIso(safeValue), min), [safeValue, min]);

  const years = useMemo(() => jalaliYearRange(min), [min]);
  const daysInMonth = useMemo(
    () => jalaliMonthLength(selected.jy, selected.jm),
    [selected.jy, selected.jm],
  );

  const emit = useCallback(
    (next: JalaliYmd): void => {
      const clamped = clampJalaliToMin(next, min);
      const iso = gregorianIsoFromJalali(clamped.jy, clamped.jm, clamped.jd);
      onChange(iso);
    },
    [min, onChange],
  );

  const minJ = useMemo(() => jalaliFromGregorianIso(min), [min]);

  const dayOptions = useMemo(() => {
    const opts: number[] = [];
    for (let d = 1; d <= daysInMonth; d++) {
      const iso = gregorianIsoFromJalali(selected.jy, selected.jm, d);
      if (compareGregorianIso(iso, min) >= 0) opts.push(d);
    }
    return opts.length ? opts : [Math.min(selected.jd, daysInMonth)];
  }, [daysInMonth, min, selected.jm, selected.jy, selected.jd]);

  const summary = formatJalaliLong(safeValue, months, true);

  return (
    <div className={`gro-jalali-date${className ? ` ${className}` : ''}`} dir="rtl" lang="fa">
      <div className="gro-jalali-date__fields">
        <label className="gro-jalali-date__field">
          <span className="gro-jalali-date__label">{t(cfg, 'jalaliDay', 'Day')}</span>
          <select
            className="gro-jalali-date__select"
            value={String(Math.min(selected.jd, daysInMonth))}
            aria-label={t(cfg, 'jalaliDay', 'Day')}
            onChange={(ev) => emit({ ...selected, jd: parseInt(ev.target.value, 10) || 1 })}
          >
            {dayOptions.map((d) => (
              <option key={d} value={d}>
                {toPersianDigits(d)}
              </option>
            ))}
          </select>
        </label>
        <label className="gro-jalali-date__field">
          <span className="gro-jalali-date__label">{t(cfg, 'jalaliMonth', 'Month')}</span>
          <select
            className="gro-jalali-date__select"
            value={String(selected.jm)}
            aria-label={t(cfg, 'jalaliMonth', 'Month')}
            onChange={(ev) => {
              const jm = parseInt(ev.target.value, 10) || 1;
              const maxDay = jalaliMonthLength(selected.jy, jm);
              emit({ ...selected, jm, jd: Math.min(selected.jd, maxDay) });
            }}
          >
            {months.map((name, i) => {
              const jm = i + 1;
              if (selected.jy > minJ.jy || (selected.jy === minJ.jy && jm >= minJ.jm)) {
                return (
                  <option key={jm} value={jm}>
                    {name}
                  </option>
                );
              }
              return null;
            })}
          </select>
        </label>
        <label className="gro-jalali-date__field">
          <span className="gro-jalali-date__label">{t(cfg, 'jalaliYear', 'Year')}</span>
          <select
            className="gro-jalali-date__select"
            value={String(selected.jy)}
            aria-label={t(cfg, 'jalaliYear', 'Year')}
            onChange={(ev) => {
              const jy = parseInt(ev.target.value, 10) || selected.jy;
              const maxDay = jalaliMonthLength(jy, selected.jm);
              emit({ ...selected, jy, jd: Math.min(selected.jd, maxDay) });
            }}
          >
            {years.map((y) => (
              <option key={y} value={y}>
                {toPersianDigits(y)}
              </option>
            ))}
          </select>
        </label>
      </div>
      <p className="gro-jalali-date__summary" aria-live="polite">
        {summary}
      </p>
    </div>
  );
}
