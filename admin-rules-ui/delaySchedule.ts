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

/** Map legacy `delay_seconds` from default.rules.json into the visual schedule model. */
export function delayScheduleFromSeconds(totalSeconds: number, ref = new Date()): ActionDelaySchedule {
  const s = defaultDelaySchedule();
  if (totalSeconds < 1) {
    return s;
  }
  s.active = true;

  const wholeDays = Math.floor(totalSeconds / 86400);
  const rem = totalSeconds % 86400;

  if (wholeDays > 0 && rem === 0) {
    s.dateMode = 'days';
    s.days = wholeDays;
    s.timeMode = 'none';
    s.relativeAmount = 0;
    s.customTime = '';
    return s;
  }

  if (wholeDays > 0 && rem > 0 && rem % 60 === 0) {
    const hours = Math.floor(rem / 3600);
    const minutes = Math.floor((rem % 3600) / 60);
    s.dateMode = 'days';
    s.days = wholeDays;
    s.timeMode = 'custom';
    s.customTime = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
    s.relativeAmount = 0;
    return s;
  }

  if (rem % 3600 === 0 && rem > 0) {
    s.dateMode = 'today';
    s.days = 0;
    s.timeMode = 'hours';
    s.relativeAmount = rem / 3600;
    return s;
  }

  if (rem % 60 === 0 && rem > 0) {
    s.dateMode = 'today';
    s.days = 0;
    s.timeMode = 'minutes';
    s.relativeAmount = rem / 60;
    return s;
  }

  if (wholeDays > 0) {
    s.dateMode = 'days';
    s.days = wholeDays;
  }

  s.timeMode = 'seconds';
  s.relativeAmount = rem > 0 ? rem : totalSeconds;
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

function hasClockTime(schedule: ActionDelaySchedule): boolean {
  return schedule.customTime.trim() !== '';
}

/**
 * Seconds from the rule match moment until the scheduled run.
 * @param ref When the triggering event occurs (not admin "now" for preview copy).
 */
export function computeDelaySeconds(schedule: ActionDelaySchedule, ref = new Date()): number {
  if (!schedule.active) {
    return 0;
  }

  let total = 0;

  if (hasClockTime(schedule)) {
    const target = resolveTargetDateTime(schedule, ref);
    total = Math.max(0, Math.floor((target.getTime() - ref.getTime()) / 1000));
  } else if (schedule.dateMode === 'days') {
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
    target = startOfLocalDay(ref);
    target.setDate(target.getDate() + Math.max(0, schedule.days));
  } else {
    target = startOfLocalDay(ref);
  }
  const [hh, mm] = schedule.customTime.split(':').map((x) => parseInt(x, 10) || 0);
  target.setHours(hh, mm, 0, 0);
  if (target.getTime() <= ref.getTime()) {
    target.setDate(target.getDate() + 1);
  }
  return target;
}

function formatClockTime(d: Date): string {
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
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

function daysLabel(schedule: ActionDelaySchedule, cfg: GroRulesEditorConfig): string {
  const n = Math.max(0, schedule.days);
  return n === 1
    ? stringT(cfg, 'delayOneDay', '1 day')
    : stringT(cfg, 'delayDays', '%s days').replace('%s', String(n));
}

function hasExtraRelativeDelay(schedule: ActionDelaySchedule): boolean {
  return (
    schedule.timeMode === 'seconds' ||
    schedule.timeMode === 'minutes' ||
    schedule.timeMode === 'hours'
  ) && schedule.relativeAmount > 0;
}

function afterMatchPhrase(cfg: GroRulesEditorConfig): string {
  return stringT(cfg, 'delayAfterMatch', 'after the rule matches');
}

function appendExtraRelative(schedule: ActionDelaySchedule, cfg: GroRulesEditorConfig, msg: string): string {
  if (!hasExtraRelativeDelay(schedule)) {
    return msg;
  }
  const extraSec =
    schedule.timeMode === 'seconds'
      ? schedule.relativeAmount
      : schedule.timeMode === 'minutes'
        ? schedule.relativeAmount * 60
        : schedule.relativeAmount * 3600;
  return `${msg} ${stringT(cfg, 'delayPreviewPlusExtra', '(plus %s more)').replace('%s', formatRelativeDuration(extraSec, cfg))}`;
}

function formatRelativeOnlyOffset(schedule: ActionDelaySchedule, cfg: GroRulesEditorConfig): string {
  const amount = Math.max(0, schedule.relativeAmount);
  if (schedule.timeMode === 'minutes' && amount > 0) {
    return amount === 1
      ? stringT(cfg, 'delayOneMinute', '1 minute')
      : stringT(cfg, 'delayMinutes', '%s minutes').replace('%s', String(amount));
  }
  if (schedule.timeMode === 'hours' && amount > 0) {
    return amount === 1
      ? stringT(cfg, 'delayOneHour', '1 hour')
      : stringT(cfg, 'delayHours', '%s hours').replace('%s', String(amount));
  }
  if (schedule.timeMode === 'seconds' && amount > 0) {
    return amount === 1
      ? stringT(cfg, 'delayOneSecond', '1 second')
      : stringT(cfg, 'delaySecondsUnit', '%s seconds').replace('%s', String(amount));
  }
  return '';
}

/**
 * Preview text: delay is calculated from when the rule matches, not from the current admin time.
 */
export function formatDelayPreview(
  schedule: ActionDelaySchedule,
  cfg: GroRulesEditorConfig,
): string {
  if (!schedule.active) {
    return stringT(cfg, 'delayImmediate', 'Immediately when the rule matches');
  }

  const when = afterMatchPhrase(cfg);
  const clock = schedule.customTime.trim();

  if (clock !== '') {
    if (schedule.dateMode === 'days' && schedule.days > 0) {
      return appendExtraRelative(
        schedule,
        cfg,
        stringT(cfg, 'delayPreviewDaysAt', 'In %1$s at %2$s %3$s')
          .replace('%1$s', daysLabel(schedule, cfg))
          .replace('%2$s', clock)
          .replace('%3$s', when),
      );
    }
    if (schedule.dateMode === 'custom' && schedule.customDate.trim() !== '') {
      const dateStr = formatPreviewDate(parseLocalDate(schedule.customDate, new Date()), cfg);
      return appendExtraRelative(
        schedule,
        cfg,
        stringT(cfg, 'delayPreviewDateAt', 'On %1$s at %2$s %3$s')
          .replace('%1$s', dateStr)
          .replace('%2$s', clock)
          .replace('%3$s', when),
      );
    }
    return appendExtraRelative(
      schedule,
      cfg,
      stringT(cfg, 'delayPreviewTodayAt', 'On the same day at %1$s %2$s')
        .replace('%1$s', clock)
        .replace('%2$s', when),
    );
  }

  if (schedule.dateMode === 'days' && schedule.days > 0) {
    return appendExtraRelative(
      schedule,
      cfg,
      stringT(cfg, 'delayPreviewDaysOnly', 'In %1$s %2$s')
        .replace('%1$s', daysLabel(schedule, cfg))
        .replace('%2$s', when),
    );
  }

  if (schedule.dateMode === 'custom' && schedule.customDate.trim() !== '') {
    const dateStr = formatPreviewDate(parseLocalDate(schedule.customDate, new Date()), cfg);
    return appendExtraRelative(
      schedule,
      cfg,
      stringT(cfg, 'delayPreviewOnDate', 'On %1$s %2$s').replace('%1$s', dateStr).replace('%2$s', when),
    );
  }

  const relative = formatRelativeOnlyOffset(schedule, cfg);
  if (relative !== '') {
    return appendExtraRelative(
      schedule,
      cfg,
      stringT(cfg, 'delayPreviewFromMatch', '%1$s %2$s').replace('%1$s', relative).replace('%2$s', when),
    );
  }

  return when;
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

  if (hasClockTime(schedule) && schedule.dateMode === 'days' && schedule.days > 0) {
    return stringT(cfg, 'delaySummaryDaysAt', 'In %1$s at %2$s')
      .replace('%1$s', daysLabel(schedule, cfg))
      .replace('%2$s', schedule.customTime);
  }

  if (hasClockTime(schedule)) {
    return stringT(cfg, 'delaySummaryAt', 'At %s').replace('%s', schedule.customTime);
  }

  if (schedule.dateMode === 'days' && schedule.days > 0) {
    return daysLabel(schedule, cfg);
  }

  return formatRelativeDuration(delaySeconds > 0 ? delaySeconds : computeDelaySeconds(schedule), cfg);
}
