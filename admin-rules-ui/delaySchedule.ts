import { formatJalaliLong, gregorianIsoFromYmd } from './jalaliCalendar';
import type { GroRulesEditorConfig } from './ruleModel';
import { stringT } from './ruleModel';

function formatPreviewDate(d: Date, cfg: GroRulesEditorConfig): string {
  if (cfg.useJalaliCalendar) {
    const iso = gregorianIsoFromYmd({
      gy: d.getFullYear(),
      gm: d.getMonth() + 1,
      gd: d.getDate(),
    });
    const months = cfg.jalaliMonthNames?.length === 12 ? cfg.jalaliMonthNames : undefined;
    return formatJalaliLong(iso, months);
  }
  return d.toLocaleDateString();
}

function formatPreviewTime(d: Date, cfg: GroRulesEditorConfig): string {
  const locale = cfg.useJalaliCalendar ? 'fa-IR' : undefined;
  return d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
}

export type ActionDelayDateMode = 'today' | 'days' | 'custom';
export type ActionDelayTimeMode = 'none' | 'seconds' | 'minutes' | 'hours' | 'custom';

export interface ActionDelaySchedule {
  /** false = delay_seconds 0 (run immediately when rule matches). */
  active: boolean;
  dateMode: ActionDelayDateMode;
  /** Used when dateMode === 'days'. */
  days: number;
  /** YYYY-MM-DD, used when dateMode === 'custom'. */
  customDate: string;
  timeMode: ActionDelayTimeMode;
  /** Used when timeMode is seconds | minutes | hours. */
  relativeAmount: number;
  /** HH:mm (24h), used when timeMode === 'custom'. */
  customTime: string;
}

const DELAY_PARAM_KEY = '_delay';

export function defaultDelaySchedule(): ActionDelaySchedule {
  return {
    active: false,
    dateMode: 'today',
    days: 1,
    customDate: todayIsoDate(),
    timeMode: 'none',
    relativeAmount: 0,
    customTime: '',
  };
}

export function todayIsoDate(ref = new Date()): string {
  const y = ref.getFullYear();
  const m = String(ref.getMonth() + 1).padStart(2, '0');
  const d = String(ref.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

export function delayScheduleFromSeconds(totalSeconds: number, ref = new Date()): ActionDelaySchedule {
  const s = defaultDelaySchedule();
  if (totalSeconds < 1) {
    return s;
  }
  s.active = true;
  const days = Math.floor(totalSeconds / 86400);
  const rem = totalSeconds % 86400;
  if (days > 0) {
    s.dateMode = 'days';
    s.days = days;
  } else {
    s.dateMode = 'today';
    s.days = 0;
  }
  if (rem === 0) {
    s.timeMode = 'none';
    s.relativeAmount = 0;
    return s;
  }
  if (rem % 3600 === 0) {
    s.timeMode = 'hours';
    s.relativeAmount = rem / 3600;
    return s;
  }
  if (rem % 60 === 0) {
    s.timeMode = 'minutes';
    s.relativeAmount = rem / 60;
    return s;
  }
  s.timeMode = 'seconds';
  s.relativeAmount = rem;
  return s;
}

export function parseDelayFromParams(params: Record<string, unknown>): ActionDelaySchedule | null {
  const raw = params[DELAY_PARAM_KEY];
  if (!raw || typeof raw !== 'object') return null;
  const o = raw as Record<string, unknown>;
  const dateMode = o.dateMode;
  const timeMode = o.timeMode;
  return {
    active: Boolean(o.active),
    dateMode:
      dateMode === 'days' || dateMode === 'custom' || dateMode === 'today' ? dateMode : 'today',
    days: Math.max(0, Math.min(365, parseInt(String(o.days ?? 0), 10) || 0)),
    customDate: typeof o.customDate === 'string' && o.customDate !== '' ? o.customDate : todayIsoDate(),
    timeMode:
      timeMode === 'seconds' ||
      timeMode === 'minutes' ||
      timeMode === 'hours' ||
      timeMode === 'custom' ||
      timeMode === 'none'
        ? timeMode
        : 'none',
    relativeAmount: Math.max(0, parseInt(String(o.relativeAmount ?? 0), 10) || 0),
    customTime: typeof o.customTime === 'string' ? o.customTime : '',
  };
}

export function stripDelayFromParams(params: Record<string, unknown>): Record<string, unknown> {
  const out = { ...params };
  delete out[DELAY_PARAM_KEY];
  return out;
}

export function mergeDelayIntoParams(
  params: Record<string, unknown>,
  schedule: ActionDelaySchedule,
): Record<string, unknown> {
  const out = stripDelayFromParams(params);
  if (schedule.active) {
    out[DELAY_PARAM_KEY] = { ...schedule };
  }
  return out;
}

/** Seconds from reference moment until the scheduled run (admin preview uses browser "now"). */
export function computeDelaySeconds(schedule: ActionDelaySchedule, ref = new Date()): number {
  if (!schedule.active) {
    return 0;
  }

  if (schedule.timeMode === 'custom' && schedule.customTime.trim() !== '') {
    const target = resolveTargetDateTime(schedule, ref);
    return Math.max(0, Math.floor((target.getTime() - ref.getTime()) / 1000));
  }

  let total = 0;

  if (schedule.dateMode === 'days') {
    total += Math.max(0, schedule.days) * 86400;
  } else if (schedule.dateMode === 'custom' && schedule.customDate.trim() !== '') {
    const targetDay = startOfLocalDay(parseLocalDate(schedule.customDate, ref));
    const nowDay = startOfLocalDay(ref);
    total += Math.max(0, Math.floor((targetDay.getTime() - nowDay.getTime()) / 1000));
  }

  const amount = Math.max(0, schedule.relativeAmount);
  if (schedule.timeMode === 'seconds') {
    total += amount;
  } else if (schedule.timeMode === 'minutes') {
    total += amount * 60;
  } else if (schedule.timeMode === 'hours') {
    total += amount * 3600;
  }

  return total;
}

function resolveTargetDateTime(schedule: ActionDelaySchedule, ref: Date): Date {
  let target: Date;
  if (schedule.dateMode === 'custom' && schedule.customDate.trim() !== '') {
    target = parseLocalDate(schedule.customDate, ref);
  } else if (schedule.dateMode === 'days') {
    target = new Date(ref);
    target.setDate(target.getDate() + Math.max(0, schedule.days));
  } else {
    target = new Date(ref);
  }
  const [hh, mm] = schedule.customTime.split(':').map((x) => parseInt(x, 10) || 0);
  target.setHours(hh, mm, 0, 0);
  if (target.getTime() <= ref.getTime() && schedule.dateMode === 'today') {
    target.setDate(target.getDate() + 1);
  }
  return target;
}

function parseLocalDate(iso: string, fallback: Date): Date {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso.trim());
  if (!m) return new Date(fallback);
  return new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10));
}

function startOfLocalDay(d: Date): Date {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

function formatDurationParts(totalSeconds: number): { days: number; hours: number; minutes: number; seconds: number } {
  let s = Math.max(0, Math.floor(totalSeconds));
  const days = Math.floor(s / 86400);
  s %= 86400;
  const hours = Math.floor(s / 3600);
  s %= 3600;
  const minutes = Math.floor(s / 60);
  const seconds = s % 60;
  return { days, hours, minutes, seconds };
}

export function formatDelayPreview(
  schedule: ActionDelaySchedule,
  cfg: GroRulesEditorConfig,
  ref = new Date(),
): string {
  if (!schedule.active) {
    return stringT(cfg, 'delayImmediate', 'Immediately when the rule matches');
  }

  const total = computeDelaySeconds(schedule, ref);

  if (schedule.timeMode === 'custom' && schedule.customTime.trim() !== '') {
    const target = resolveTargetDateTime(schedule, ref);
    const dateStr = formatPreviewDate(target, cfg);
    const timeStr = formatPreviewTime(target, cfg);
    return stringT(cfg, 'delayPreviewAt', 'Scheduled at %1$s %2$s (about %3$s from now)')
      .replace('%1$s', dateStr)
      .replace('%2$s', timeStr)
      .replace('%3$s', formatRelativeDuration(total, cfg));
  }

  if (total < 60) {
    return stringT(cfg, 'delayPreviewSeconds', 'About %s seconds after the rule matches').replace(
      '%s',
      String(total),
    );
  }

  return stringT(cfg, 'delayPreviewRelative', 'About %s after the rule matches').replace(
    '%s',
    formatRelativeDuration(total, cfg),
  );
}

export function formatRelativeDuration(totalSeconds: number, cfg: GroRulesEditorConfig): string {
  const { days, hours, minutes, seconds } = formatDurationParts(totalSeconds);
  const parts: string[] = [];
  if (days > 0) {
    parts.push(
      days === 1
        ? stringT(cfg, 'delayOneDay', '1 day')
        : stringT(cfg, 'delayDays', '%s days').replace('%s', String(days)),
    );
  }
  if (hours > 0) {
    parts.push(
      hours === 1
        ? stringT(cfg, 'delayOneHour', '1 hour')
        : stringT(cfg, 'delayHours', '%s hours').replace('%s', String(hours)),
    );
  }
  if (minutes > 0) {
    parts.push(
      minutes === 1
        ? stringT(cfg, 'delayOneMinute', '1 minute')
        : stringT(cfg, 'delayMinutes', '%s minutes').replace('%s', String(minutes)),
    );
  }
  if (seconds > 0 && days === 0 && hours === 0) {
    parts.push(
      seconds === 1
        ? stringT(cfg, 'delayOneSecond', '1 second')
        : stringT(cfg, 'delaySecondsUnit', '%s seconds').replace('%s', String(seconds)),
    );
  }
  if (parts.length === 0) {
    return stringT(cfg, 'delayImmediate', 'Immediately when the rule matches');
  }
  return parts.join(', ');
}

export function formatDelaySummary(
  schedule: ActionDelaySchedule,
  delaySeconds: number,
  cfg: GroRulesEditorConfig,
): string {
  if (!schedule.active && delaySeconds < 1) {
    return stringT(cfg, 'delayImmediateShort', 'Immediate');
  }
  return formatRelativeDuration(delaySeconds > 0 ? delaySeconds : computeDelaySeconds(schedule), cfg);
}
