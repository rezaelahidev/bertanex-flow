import { JalaliDateInput } from './JalaliDateInput';
import { useCfg } from './ConfigContext';
import React from 'react';

export interface DateInputProps {
  value: string;
  min: string;
  onChange: (isoGregorian: string) => void;
  className?: string;
}

/** Gregorian native picker, or Jalali selects when `useJalaliCalendar` is enabled (RTL Persian admin). */
export function DateInput({ value, min, onChange, className = '' }: DateInputProps): React.ReactElement {
  const cfg = useCfg();

  if (cfg.useJalaliCalendar) {
    return <JalaliDateInput value={value} min={min} onChange={onChange} className={className} />;
  }

  return (
    <input
      type="date"
      className={className || 'gro-action-delay__date'}
      min={min}
      value={value}
      onChange={(ev) => onChange(ev.target.value)}
    />
  );
}
