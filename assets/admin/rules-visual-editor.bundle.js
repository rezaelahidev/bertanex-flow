(function() {
  "use strict";
  const element = window.wp.element;
  const useState = element.useState;
  const useEffect = element.useEffect;
  const useLayoutEffect = element.useLayoutEffect;
  const useMemo = element.useMemo;
  const useCallback = element.useCallback;
  const useRef = element.useRef;
  element.useId;
  element.createElement;
  element.Fragment;
  const StrictMode = element.StrictMode;
  element.forwardRef;
  const createContext = element.createContext;
  const useContext = element.useContext;
  function createRoot(container) {
    return window.wp.element.createRoot(container);
  }
  const JALALI_MONTH_NAMES_FA = [
    "فروردین",
    "اردیبهشت",
    "خرداد",
    "تیر",
    "مرداد",
    "شهریور",
    "مهر",
    "آبان",
    "آذر",
    "دی",
    "بهمن",
    "اسفند"
  ];
  const PERSIAN_DIGITS = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];
  function toPersianDigits(value) {
    return String(value).replace(/\d/g, (d) => PERSIAN_DIGITS[parseInt(d, 10)] ?? d);
  }
  function isLeapJalaliYear(jy) {
    const r = jy % 33;
    return r === 1 || r === 5 || r === 9 || r === 13 || r === 17 || r === 22 || r === 26 || r === 30;
  }
  function jalaliMonthLength(jy, jm) {
    if (jm < 1 || jm > 12) return 0;
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    return isLeapJalaliYear(jy) ? 30 : 29;
  }
  function toJalaali(gy, gm, gd) {
    const gy2 = gm > 2 ? gy + 1 : gy;
    let days = 355666 + 365 * gy + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) + gd + [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334][gm - 1];
    let jy = -1595 + 33 * Math.floor(days / 12053);
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
      jy += Math.floor((days - 1) / 365);
      days = (days - 1) % 365;
    }
    let jm;
    let jd;
    if (days < 186) {
      jm = 1 + Math.floor(days / 31);
      jd = 1 + days % 31;
    } else {
      jm = 7 + Math.floor((days - 186) / 30);
      jd = 1 + (days - 186) % 30;
    }
    return { jy, jm, jd };
  }
  function toGregorian(jy, jm, jd) {
    let jy2 = jy + 1595;
    let days = -355668 + 365 * jy2 + Math.floor(jy2 / 33) * 8 + Math.floor((jy2 % 33 + 3) / 4) + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
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
      0,
      31,
      leap ? 29 : 28,
      31,
      30,
      31,
      30,
      31,
      31,
      30,
      31,
      30,
      31
    ];
    let gm = 0;
    for (gm = 0; gm < 13 && days >= sal_a[gm]; gm++) {
      days -= sal_a[gm];
    }
    return { gy, gm, gd: days + 1 };
  }
  function parseGregorianIso(iso) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso.trim());
    if (!m) return null;
    const gy = parseInt(m[1], 10);
    const gm = parseInt(m[2], 10);
    const gd = parseInt(m[3], 10);
    if (gm < 1 || gm > 12 || gd < 1 || gd > 31) return null;
    return { gy, gm, gd };
  }
  function gregorianIsoFromYmd({ gy, gm, gd }) {
    return `${String(gy)}-${String(gm).padStart(2, "0")}-${String(gd).padStart(2, "0")}`;
  }
  function jalaliFromGregorianIso(iso, fallback = /* @__PURE__ */ new Date()) {
    const parsed = parseGregorianIso(iso);
    if (!parsed) {
      const d = fallback;
      return toJalaali(d.getFullYear(), d.getMonth() + 1, d.getDate());
    }
    return toJalaali(parsed.gy, parsed.gm, parsed.gd);
  }
  function gregorianIsoFromJalali(jy, jm, jd) {
    const g = toGregorian(jy, jm, jd);
    return gregorianIsoFromYmd(g);
  }
  function compareGregorianIso(a, b) {
    return a.localeCompare(b);
  }
  function clampGregorianIsoToMin(iso, minIso) {
    return compareGregorianIso(iso, minIso) < 0 ? minIso : iso;
  }
  function formatJalaliLong(isoGregorian, monthNames = JALALI_MONTH_NAMES_FA, persianDigits = true) {
    const { jy, jm, jd } = jalaliFromGregorianIso(isoGregorian);
    const month = monthNames[jm - 1] ?? String(jm);
    const dayStr = persianDigits ? toPersianDigits(jd) : String(jd);
    const yearStr = persianDigits ? toPersianDigits(jy) : String(jy);
    return `${dayStr} ${month} ${yearStr}`;
  }
  function jalaliYearRange(minIso, maxYearsAhead = 2, ref = /* @__PURE__ */ new Date()) {
    const minJ = jalaliFromGregorianIso(minIso, ref);
    const maxJ = toJalaali(ref.getFullYear() + maxYearsAhead, 12, 29);
    const out = [];
    for (let y = minJ.jy; y <= maxJ.jy; y++) {
      out.push(y);
    }
    return out.length ? out : [minJ.jy];
  }
  function formatPreviewDate(d, cfg) {
    if (cfg.useJalaliCalendar) {
      const iso = gregorianIsoFromYmd({
        gy: d.getFullYear(),
        gm: d.getMonth() + 1,
        gd: d.getDate()
      });
      const months = cfg.jalaliMonthNames?.length === 12 ? cfg.jalaliMonthNames : void 0;
      return formatJalaliLong(iso, months);
    }
    return d.toLocaleDateString();
  }
  const DELAY_PARAM_KEY = "_delay";
  function defaultDelaySchedule() {
    return {
      active: false,
      dateMode: "today",
      days: 1,
      customDate: todayIsoDate(),
      timeMode: "none",
      relativeAmount: 0,
      customTime: ""
    };
  }
  function todayIsoDate(ref = /* @__PURE__ */ new Date()) {
    const y = ref.getFullYear();
    const m = String(ref.getMonth() + 1).padStart(2, "0");
    const d = String(ref.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
  }
  function delayScheduleFromSeconds(totalSeconds, ref = /* @__PURE__ */ new Date()) {
    const s = defaultDelaySchedule();
    if (totalSeconds < 1) {
      return s;
    }
    s.active = true;
    const wholeDays = Math.floor(totalSeconds / 86400);
    const rem = totalSeconds % 86400;
    if (wholeDays > 0 && rem === 0) {
      s.dateMode = "days";
      s.days = wholeDays;
      s.timeMode = "none";
      s.relativeAmount = 0;
      s.customTime = "";
      return s;
    }
    if (wholeDays > 0 && rem > 0 && rem % 60 === 0) {
      const hours = Math.floor(rem / 3600);
      const minutes = Math.floor(rem % 3600 / 60);
      s.dateMode = "days";
      s.days = wholeDays;
      s.timeMode = "custom";
      s.customTime = `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}`;
      s.relativeAmount = 0;
      return s;
    }
    if (rem % 3600 === 0 && rem > 0) {
      s.dateMode = "today";
      s.days = 0;
      s.timeMode = "hours";
      s.relativeAmount = rem / 3600;
      return s;
    }
    if (rem % 60 === 0 && rem > 0) {
      s.dateMode = "today";
      s.days = 0;
      s.timeMode = "minutes";
      s.relativeAmount = rem / 60;
      return s;
    }
    if (wholeDays > 0) {
      s.dateMode = "days";
      s.days = wholeDays;
    }
    s.timeMode = "seconds";
    s.relativeAmount = rem > 0 ? rem : totalSeconds;
    return s;
  }
  function parseDelayFromParams(params) {
    const raw = params[DELAY_PARAM_KEY];
    if (!raw || typeof raw !== "object") return null;
    const o = raw;
    const dateMode = o.dateMode;
    const timeMode = o.timeMode;
    return {
      active: Boolean(o.active),
      dateMode: dateMode === "days" || dateMode === "custom" || dateMode === "today" ? dateMode : "today",
      days: Math.max(0, Math.min(365, parseInt(String(o.days ?? 0), 10) || 0)),
      customDate: typeof o.customDate === "string" && o.customDate !== "" ? o.customDate : todayIsoDate(),
      timeMode: timeMode === "seconds" || timeMode === "minutes" || timeMode === "hours" || timeMode === "custom" || timeMode === "none" ? timeMode : "none",
      relativeAmount: Math.max(0, parseInt(String(o.relativeAmount ?? 0), 10) || 0),
      customTime: typeof o.customTime === "string" ? o.customTime : ""
    };
  }
  function stripDelayFromParams(params) {
    const out = { ...params };
    delete out[DELAY_PARAM_KEY];
    return out;
  }
  function mergeDelayIntoParams(params, schedule) {
    const out = stripDelayFromParams(params);
    if (schedule.active) {
      out[DELAY_PARAM_KEY] = { ...schedule };
    }
    return out;
  }
  function hasClockTime(schedule) {
    return schedule.customTime.trim() !== "";
  }
  function computeDelaySeconds(schedule, ref = /* @__PURE__ */ new Date()) {
    if (!schedule.active) {
      return 0;
    }
    let total = 0;
    if (hasClockTime(schedule)) {
      const target = resolveTargetDateTime(schedule, ref);
      total = Math.max(0, Math.floor((target.getTime() - ref.getTime()) / 1e3));
    } else if (schedule.dateMode === "days") {
      total += Math.max(0, schedule.days) * 86400;
    } else if (schedule.dateMode === "custom" && schedule.customDate.trim() !== "") {
      const targetDay = startOfLocalDay(parseLocalDate(schedule.customDate, ref));
      const nowDay = startOfLocalDay(ref);
      total += Math.max(0, Math.floor((targetDay.getTime() - nowDay.getTime()) / 1e3));
    }
    const amount = Math.max(0, schedule.relativeAmount);
    if (schedule.timeMode === "seconds") {
      total += amount;
    } else if (schedule.timeMode === "minutes") {
      total += amount * 60;
    } else if (schedule.timeMode === "hours") {
      total += amount * 3600;
    }
    return total;
  }
  function resolveTargetDateTime(schedule, ref) {
    let target;
    if (schedule.dateMode === "custom" && schedule.customDate.trim() !== "") {
      target = parseLocalDate(schedule.customDate, ref);
    } else if (schedule.dateMode === "days") {
      target = startOfLocalDay(ref);
      target.setDate(target.getDate() + Math.max(0, schedule.days));
    } else {
      target = startOfLocalDay(ref);
    }
    const [hh, mm] = schedule.customTime.split(":").map((x) => parseInt(x, 10) || 0);
    target.setHours(hh, mm, 0, 0);
    if (target.getTime() <= ref.getTime()) {
      target.setDate(target.getDate() + 1);
    }
    return target;
  }
  function parseLocalDate(iso, fallback) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso.trim());
    if (!m) return new Date(fallback);
    return new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10));
  }
  function startOfLocalDay(d) {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate());
  }
  function formatDurationParts(totalSeconds) {
    let s = Math.max(0, Math.floor(totalSeconds));
    const days = Math.floor(s / 86400);
    s %= 86400;
    const hours = Math.floor(s / 3600);
    s %= 3600;
    const minutes = Math.floor(s / 60);
    const seconds = s % 60;
    return { days, hours, minutes, seconds };
  }
  function daysLabel(schedule, cfg) {
    const n = Math.max(0, schedule.days);
    return n === 1 ? stringT(cfg, "delayOneDay", "1 day") : stringT(cfg, "delayDays", "%s days").replace("%s", String(n));
  }
  function hasExtraRelativeDelay(schedule) {
    return (schedule.timeMode === "seconds" || schedule.timeMode === "minutes" || schedule.timeMode === "hours") && schedule.relativeAmount > 0;
  }
  function afterMatchPhrase(cfg) {
    return stringT(cfg, "delayAfterMatch", "after the rule matches");
  }
  function appendExtraRelative(schedule, cfg, msg) {
    if (!hasExtraRelativeDelay(schedule)) {
      return msg;
    }
    const extraSec = schedule.timeMode === "seconds" ? schedule.relativeAmount : schedule.timeMode === "minutes" ? schedule.relativeAmount * 60 : schedule.relativeAmount * 3600;
    return `${msg} ${stringT(cfg, "delayPreviewPlusExtra", "(plus %s more)").replace("%s", formatRelativeDuration(extraSec, cfg))}`;
  }
  function formatRelativeOnlyOffset(schedule, cfg) {
    const amount = Math.max(0, schedule.relativeAmount);
    if (schedule.timeMode === "minutes" && amount > 0) {
      return amount === 1 ? stringT(cfg, "delayOneMinute", "1 minute") : stringT(cfg, "delayMinutes", "%s minutes").replace("%s", String(amount));
    }
    if (schedule.timeMode === "hours" && amount > 0) {
      return amount === 1 ? stringT(cfg, "delayOneHour", "1 hour") : stringT(cfg, "delayHours", "%s hours").replace("%s", String(amount));
    }
    if (schedule.timeMode === "seconds" && amount > 0) {
      return amount === 1 ? stringT(cfg, "delayOneSecond", "1 second") : stringT(cfg, "delaySecondsUnit", "%s seconds").replace("%s", String(amount));
    }
    return "";
  }
  function formatDelayPreview(schedule, cfg) {
    if (!schedule.active) {
      return stringT(cfg, "delayImmediate", "Immediately when the rule matches");
    }
    const when = afterMatchPhrase(cfg);
    const clock = schedule.customTime.trim();
    if (clock !== "") {
      if (schedule.dateMode === "days" && schedule.days > 0) {
        return appendExtraRelative(
          schedule,
          cfg,
          stringT(cfg, "delayPreviewDaysAt", "In %1$s at %2$s %3$s").replace("%1$s", daysLabel(schedule, cfg)).replace("%2$s", clock).replace("%3$s", when)
        );
      }
      if (schedule.dateMode === "custom" && schedule.customDate.trim() !== "") {
        const dateStr = formatPreviewDate(parseLocalDate(schedule.customDate, /* @__PURE__ */ new Date()), cfg);
        return appendExtraRelative(
          schedule,
          cfg,
          stringT(cfg, "delayPreviewDateAt", "On %1$s at %2$s %3$s").replace("%1$s", dateStr).replace("%2$s", clock).replace("%3$s", when)
        );
      }
      return appendExtraRelative(
        schedule,
        cfg,
        stringT(cfg, "delayPreviewTodayAt", "On the same day at %1$s %2$s").replace("%1$s", clock).replace("%2$s", when)
      );
    }
    if (schedule.dateMode === "days" && schedule.days > 0) {
      return appendExtraRelative(
        schedule,
        cfg,
        stringT(cfg, "delayPreviewDaysOnly", "In %1$s %2$s").replace("%1$s", daysLabel(schedule, cfg)).replace("%2$s", when)
      );
    }
    if (schedule.dateMode === "custom" && schedule.customDate.trim() !== "") {
      const dateStr = formatPreviewDate(parseLocalDate(schedule.customDate, /* @__PURE__ */ new Date()), cfg);
      return appendExtraRelative(
        schedule,
        cfg,
        stringT(cfg, "delayPreviewOnDate", "On %1$s %2$s").replace("%1$s", dateStr).replace("%2$s", when)
      );
    }
    const relative = formatRelativeOnlyOffset(schedule, cfg);
    if (relative !== "") {
      return appendExtraRelative(
        schedule,
        cfg,
        stringT(cfg, "delayPreviewFromMatch", "%1$s %2$s").replace("%1$s", relative).replace("%2$s", when)
      );
    }
    return when;
  }
  function formatRelativeDuration(totalSeconds, cfg) {
    const { days, hours, minutes, seconds } = formatDurationParts(totalSeconds);
    const parts = [];
    if (days > 0) {
      parts.push(
        days === 1 ? stringT(cfg, "delayOneDay", "1 day") : stringT(cfg, "delayDays", "%s days").replace("%s", String(days))
      );
    }
    if (hours > 0) {
      parts.push(
        hours === 1 ? stringT(cfg, "delayOneHour", "1 hour") : stringT(cfg, "delayHours", "%s hours").replace("%s", String(hours))
      );
    }
    if (minutes > 0) {
      parts.push(
        minutes === 1 ? stringT(cfg, "delayOneMinute", "1 minute") : stringT(cfg, "delayMinutes", "%s minutes").replace("%s", String(minutes))
      );
    }
    if (seconds > 0 && days === 0 && hours === 0) {
      parts.push(
        seconds === 1 ? stringT(cfg, "delayOneSecond", "1 second") : stringT(cfg, "delaySecondsUnit", "%s seconds").replace("%s", String(seconds))
      );
    }
    if (parts.length === 0) {
      return stringT(cfg, "delayImmediate", "Immediately when the rule matches");
    }
    return parts.join(", ");
  }
  function formatDelaySummary(schedule, delaySeconds, cfg) {
    if (!schedule.active && delaySeconds < 1) {
      return stringT(cfg, "delayImmediateShort", "Immediate");
    }
    if (hasClockTime(schedule) && schedule.dateMode === "days" && schedule.days > 0) {
      return stringT(cfg, "delaySummaryDaysAt", "In %1$s at %2$s").replace("%1$s", daysLabel(schedule, cfg)).replace("%2$s", schedule.customTime);
    }
    if (hasClockTime(schedule)) {
      return stringT(cfg, "delaySummaryAt", "At %s").replace("%s", schedule.customTime);
    }
    if (schedule.dateMode === "days" && schedule.days > 0) {
      return daysLabel(schedule, cfg);
    }
    return formatRelativeDuration(delaySeconds > 0 ? delaySeconds : computeDelaySeconds(schedule), cfg);
  }
  const PRESET_VALUE_OPS = {
    eq: true,
    neq: true,
    gt: true,
    gte: true,
    lt: true,
    lte: true,
    starts_with: true,
    ends_with: true,
    contains: true
  };
  const OPERATORS_NO_VALUE = {
    empty: true,
    not_empty: true,
    truthy: true,
    falsy: true
  };
  function stringT(cfg, key, fallback) {
    const s = cfg.strings?.[key];
    return s !== void 0 ? String(s) : fallback;
  }
  function slugLabel(map, slug) {
    const v = map?.[slug];
    return v !== void 0 && v !== "" ? v : slug;
  }
  function normalizeValueChoice(raw) {
    if (!raw || typeof raw !== "object") return null;
    const row = raw;
    if (!Object.prototype.hasOwnProperty.call(row, "value") || !Object.prototype.hasOwnProperty.call(row, "label")) {
      return null;
    }
    const v = row.value;
    if (typeof v !== "boolean" && typeof v !== "number" && typeof v !== "string") return null;
    const label = String(row.label ?? "").trim();
    if (label === "") return null;
    return { value: v, label };
  }
  function normalizeSuggestedConditionFields(raw) {
    if (!raw?.length) return [];
    return raw.map((item) => {
      if (typeof item === "string") {
        return { name: item, label: item };
      }
      const name = String(item.name ?? "").trim();
      const label = String(item.label ?? name).trim();
      const valueType = item.valueType === "boolean" || item.valueType === "string" || item.valueType === "integer" || item.valueType === "number" ? item.valueType : void 0;
      const values = Array.isArray(item.values) ? item.values.map(normalizeValueChoice).filter((c) => c !== null) : void 0;
      return {
        name,
        label: label || name,
        valueType,
        values: values?.length ? values : void 0
      };
    }).filter((item) => item.name !== "");
  }
  function findConditionField(fields, name) {
    const key = name.trim();
    if (key === "") return void 0;
    return fields.find((f) => f.name === key);
  }
  function valueJsonForChoice(choice) {
    return JSON.stringify(choice.value);
  }
  function parseValueJson(valueJson) {
    const raw = String(valueJson ?? "").trim();
    if (raw === "") return void 0;
    try {
      return JSON.parse(raw);
    } catch {
      return void 0;
    }
  }
  function valueJsonMatchesChoice(valueJson, choice) {
    const parsed = parseValueJson(valueJson);
    if (parsed === void 0) return false;
    try {
      return JSON.stringify(parsed) === JSON.stringify(choice.value);
    } catch {
      return false;
    }
  }
  function findMatchingValueChoice(field, valueJson) {
    if (!field?.values?.length) return void 0;
    return field.values.find((c) => valueJsonMatchesChoice(valueJson, c));
  }
  function defaultValueJsonForField(field) {
    const first = field?.values?.[0];
    return first ? valueJsonForChoice(first) : "";
  }
  function operatorSupportsPresetValues(op) {
    return Boolean(PRESET_VALUE_OPS[op]);
  }
  function fieldHasPresetValues(field, op) {
    return Boolean(field?.values?.length && operatorSupportsPresetValues(op));
  }
  function conditionValueLabel(fields, fieldName, valueJson) {
    const field = findConditionField(fields, fieldName);
    const match = findMatchingValueChoice(field, valueJson);
    if (match) return match.label;
    const raw = String(valueJson ?? "").trim();
    return raw === "" ? "" : raw;
  }
  function conditionFieldLabel(fields, name) {
    const key = name.trim();
    if (key === "") return "";
    const found = fields.find((f) => f.name === key);
    return found?.label ?? key;
  }
  function parseDocumentJson(text, errMsg) {
    const data = JSON.parse(text);
    if (!data || typeof data !== "object" || !Array.isArray(data.rules)) {
      throw new Error(errMsg);
    }
    return data;
  }
  function isSimpleLeaf(leaf) {
    if (!leaf || typeof leaf !== "object") return false;
    const o = leaf;
    if (typeof o.field !== "string" || typeof o.op !== "string") return false;
    for (const k of Object.keys(o)) {
      if (!["field", "op", "value", "pattern"].includes(k)) return false;
    }
    return true;
  }
  function isSimpleConditionGroup(c) {
    if (!c || typeof c !== "object") return false;
    const o = c;
    if ("any" in o || "not" in o) return false;
    if (!Array.isArray(o.all)) return false;
    return o.all.every(isSimpleLeaf);
  }
  function isSimpleTrigger(trigger, recurrences) {
    if (trigger === null || trigger === void 0) return true;
    if (typeof trigger !== "object" || Array.isArray(trigger)) return false;
    const t2 = trigger;
    const keys = Object.keys(t2);
    if (keys.length === 0) return true;
    const hasEv = Array.isArray(t2.events);
    const hasSch = t2.schedule !== void 0 && t2.schedule !== null && typeof t2.schedule === "object";
    if (hasEv && hasSch) return false;
    if (hasSch) {
      const s = t2.schedule;
      return typeof s.hook === "string" && recurrences.includes(String(s.recurrence));
    }
    if (hasEv) {
      return t2.events.every((e) => typeof e === "string");
    }
    return false;
  }
  function ruleIsVisuallyEditable(rule, recurrences) {
    return isSimpleConditionGroup(rule.conditions) && isSimpleTrigger(rule.trigger, recurrences);
  }
  function defaultSchedule(recurrenceFallback) {
    return { hook: "", recurrence: recurrenceFallback };
  }
  function leafToRow(leaf) {
    const op = typeof leaf.op === "string" ? leaf.op : "eq";
    const row = {
      field: typeof leaf.field === "string" ? leaf.field : "",
      op,
      valueJson: "",
      pattern: typeof leaf.pattern === "string" ? leaf.pattern : ""
    };
    if (op === "regex") return row;
    if (OPERATORS_NO_VALUE[op]) return row;
    if (Object.prototype.hasOwnProperty.call(leaf, "value")) {
      try {
        row.valueJson = JSON.stringify(leaf.value);
      } catch {
        row.valueJson = String(leaf.value);
      }
    }
    return row;
  }
  function rowToLeaf(row) {
    const field = String(row.field || "").trim();
    const op = row.op || "eq";
    const leaf = { field, op };
    if (OPERATORS_NO_VALUE[op]) return leaf;
    if (op === "regex") {
      leaf.pattern = String(row.pattern || "");
      return leaf;
    }
    const raw = String(row.valueJson || "").trim();
    if (!raw) return leaf;
    leaf.value = JSON.parse(raw);
    return leaf;
  }
  function triggerToEditable(trigger, recurrences, defaultRec) {
    if (trigger && typeof trigger === "object" && trigger !== null) {
      const t2 = trigger;
      const sch = t2.schedule;
      if (sch && typeof sch === "object" && sch !== null) {
        const s = sch;
        if (typeof s.hook === "string" && s.hook !== "") {
          const rec = recurrences.includes(String(s.recurrence)) ? String(s.recurrence) : defaultRec;
          return { mode: "schedule", events: [], schedule: { hook: s.hook, recurrence: rec } };
        }
      }
      if (Array.isArray(t2.events)) {
        return {
          mode: "events",
          events: t2.events.filter((e) => typeof e === "string"),
          schedule: defaultSchedule(defaultRec)
        };
      }
    }
    return { mode: "none", events: [], schedule: defaultSchedule(defaultRec) };
  }
  function triggerFromEditable(te) {
    if (te.mode === "schedule") {
      const h = String(te.schedule.hook || "").trim();
      if (h === "") return void 0;
      return { schedule: { hook: h, recurrence: te.schedule.recurrence } };
    }
    if (te.mode === "events" && te.events.length > 0) {
      const uniq = [];
      te.events.forEach((ev) => {
        if (typeof ev === "string" && ev !== "" && !uniq.includes(ev)) uniq.push(ev);
      });
      if (uniq.length === 0) return void 0;
      return { events: uniq };
    }
    return void 0;
  }
  function actionToRow(a, fallbackType) {
    const type = typeof a.type === "string" ? a.type : fallbackType;
    const templateId = typeof a.template_id === "string" ? a.template_id : "";
    const delay = Object.prototype.hasOwnProperty.call(a, "delay_seconds") ? Number(a.delay_seconds) : 0;
    const params = Object.prototype.hasOwnProperty.call(a, "params") && a.params && typeof a.params === "object" ? a.params : {};
    let paramsJson;
    try {
      paramsJson = JSON.stringify(params);
    } catch {
      paramsJson = "{}";
    }
    const delaySec = Number.isNaN(delay) ? 0 : Math.max(0, Math.floor(delay));
    const parsedSchedule = parseDelayFromParams(params);
    let delaySchedule = parsedSchedule ?? delayScheduleFromSeconds(delaySec);
    if (delaySec > 0 && !delaySchedule.active) {
      delaySchedule = { ...delaySchedule, active: true };
    }
    return {
      type,
      template_id: templateId,
      delay_seconds: delaySec,
      delaySchedule,
      paramsJson: JSON.stringify(stripDelayFromParams(params))
    };
  }
  function rowToAction(row) {
    const out = { type: row.type };
    const tid = String(row.template_id || "").trim();
    if (tid !== "") out.template_id = tid;
    const delay = computeDelaySeconds(row.delaySchedule);
    if (delay > 0) out.delay_seconds = delay;
    const raw = String(row.paramsJson || "").trim();
    let params = {};
    if (raw !== "") {
      const parsed = JSON.parse(raw);
      if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
        params = parsed;
      }
    }
    out.params = mergeDelayIntoParams(params, row.delaySchedule);
    return out;
  }
  function ruleToEditable(rule, recurrences, actionTypes) {
    const defaultRec = recurrences[0] ?? "daily";
    const te = triggerToEditable(rule.trigger, recurrences, defaultRec);
    const condSrc = rule.conditions && typeof rule.conditions === "object" ? rule.conditions.all : void 0;
    let conds = Array.isArray(condSrc) ? condSrc.map((l) => leafToRow(l)) : [{ field: "", op: "eq", valueJson: "true", pattern: "" }];
    if (conds.length === 0) conds = [{ field: "", op: "eq", valueJson: "", pattern: "" }];
    const actionSrc = rule.actions;
    const at0 = actionTypes[0] ?? "log";
    const actions = Array.isArray(actionSrc) && actionSrc.length > 0 ? actionSrc.map((a) => actionToRow(a, at0)) : [{ type: at0, template_id: "", delay_seconds: 0, delaySchedule: defaultDelaySchedule(), paramsJson: "{}" }];
    return {
      id: typeof rule.id === "string" ? rule.id : `rule_${Date.now()}`,
      label: typeof rule.label === "string" ? rule.label : "",
      enabled: typeof rule.enabled === "boolean" ? rule.enabled : true,
      priority: typeof rule.priority === "number" ? rule.priority : parseInt(String(rule.priority ?? 10), 10) || 10,
      trigger: te,
      conditions: conds,
      actions
    };
  }
  function editableToRule(edit) {
    const conditionsAll = edit.conditions.map((c) => rowToLeaf(c));
    const actionsArr = edit.actions.map((a) => rowToAction(a));
    const tr = triggerFromEditable(edit.trigger);
    const out = {
      id: edit.id.trim() || `rule_${Date.now()}`,
      enabled: edit.enabled,
      priority: typeof edit.priority === "number" && !Number.isNaN(edit.priority) ? edit.priority : 10,
      conditions: { all: conditionsAll },
      actions: actionsArr
    };
    const lbl = edit.label.trim();
    if (lbl !== "") out.label = lbl;
    if (tr !== void 0) out.trigger = tr;
    return out;
  }
  function importRules(rawRules, recurrences, actionTypes) {
    const ui = [];
    for (const rule of rawRules) {
      if (!rule || typeof rule !== "object") continue;
      const r = rule;
      if (!ruleIsVisuallyEditable(r, recurrences)) {
        ui.push({ type: "advanced", snapshot: JSON.parse(JSON.stringify(r)) });
        continue;
      }
      ui.push({ type: "simple", editable: ruleToEditable(r, recurrences, actionTypes) });
    }
    return ui;
  }
  function serializeDocument(version, uiRules, fallbackVersion) {
    const rules = uiRules.map(
      (u) => u.type === "advanced" ? JSON.parse(JSON.stringify(u.snapshot)) : editableToRule(u.editable)
    );
    return { version: version || fallbackVersion, rules };
  }
  function newEditableRule(actionTypes, recurrences) {
    recurrences[0] ?? "daily";
    const at0 = actionTypes[0] ?? "log";
    return ruleToEditable(
      {
        id: `new_rule_${Date.now()}`,
        label: "",
        enabled: true,
        priority: 10,
        trigger: null,
        conditions: { all: [{ field: "", op: "eq", valueJson: "true", pattern: "" }] },
        actions: [{ type: at0, template_id: "", delay_seconds: 0, params: {} }]
      },
      recurrences,
      actionTypes
    );
  }
  function opsNeedValue(op) {
    return !OPERATORS_NO_VALUE[op] && op !== "regex";
  }
  function operatorOptions(cfg, current) {
    const ops = [...cfg.operators ?? ["eq"]];
    if (current && !ops.includes(current)) ops.unshift(current);
    return ops;
  }
  function parseDocumentFromTextarea(text, cfg) {
    const defaultVersion = cfg.defaultVersion ?? "1.0.0";
    const rec = cfg.recurrences ?? ["hourly", "twicedaily", "daily", "weekly"];
    const actionTypes = cfg.actionTypes ?? ["send_sms"];
    const errRoot = stringT(cfg, "errInvalidRoot", 'JSON must be an object with a "rules" array.');
    const trimmed = text.trim();
    if (trimmed === "") {
      return { version: defaultVersion, rules: [] };
    }
    const doc = parseDocumentJson(trimmed, errRoot);
    const version = typeof doc.version === "string" && doc.version !== "" ? doc.version : defaultVersion;
    const rules = importRules(doc.rules ?? [], rec, actionTypes);
    return { version, rules };
  }
  const ICONS$1 = {
    add: "plus",
    delete: "trash",
    edit: "edit",
    duplicate: "admin-page",
    up: "arrow-up-alt2",
    down: "arrow-down-alt2",
    save: "saved",
    close: "no-alt",
    condition: "filter",
    action: "controls-play",
    trigger: "calendar-alt",
    clock: "clock",
    settings: "admin-generic"
  };
  const GroButton = element.forwardRef(function GroButton2({ icon, variant = "secondary", size = "md", iconOnly = false, children, className, ...rest }, ref) {
    const Button = window.wp.components.Button;
    const wpVariant = variant === "primary" ? "primary" : variant === "secondary" ? "secondary" : "tertiary";
    return /* @__PURE__ */ element.createElement(
      Button,
      {
        ref,
        variant: wpVariant,
        isDestructive: variant === "danger",
        size: size === "sm" ? "compact" : void 0,
        __next40pxDefaultSize: size !== "sm",
        icon: icon ? ICONS$1[icon] ?? icon : void 0,
        className,
        ...rest
      },
      iconOnly ? void 0 : children
    );
  });
  const ICONS = {
    add: "plus",
    delete: "trash",
    edit: "edit",
    duplicate: "admin-page",
    up: "arrow-up-alt2",
    down: "arrow-down-alt2",
    save: "saved",
    close: "no-alt",
    condition: "filter",
    action: "controls-play",
    trigger: "calendar-alt",
    clock: "clock",
    settings: "admin-generic"
  };
  function GroIcon({ name }) {
    const Icon = window.wp.components.Icon;
    return /* @__PURE__ */ element.createElement(Icon, { icon: ICONS[name] });
  }
  function ConfirmDialog({
    isOpen,
    message,
    confirmLabel,
    cancelLabel,
    onConfirm,
    onCancel
  }) {
    if (!isOpen) return null;
    const Dialog = window.wp.components.__experimentalConfirmDialog;
    if (!Dialog) return null;
    return /* @__PURE__ */ element.createElement(
      Dialog,
      {
        isOpen: true,
        onConfirm,
        onCancel,
        confirmButtonText: confirmLabel,
        cancelButtonText: cancelLabel
      },
      message
    );
  }
  function Modal({ title, isOpen, onClose, footer, children }) {
    if (!isOpen) return null;
    const WpModal = window.wp.components.Modal;
    return /* @__PURE__ */ element.createElement(WpModal, { title, onRequestClose: onClose, size: "large", className: "gro-editor-modal" }, /* @__PURE__ */ element.createElement("div", { className: "gro-editor-modal__body" }, children), footer !== void 0 ? /* @__PURE__ */ element.createElement("div", { className: "gro-editor-modal__footer" }, footer) : null);
  }
  const CfgContext = createContext({});
  function normalizePanelConfig(raw) {
    const maxConditions = Math.max(1, Math.min(50, parseInt(String(raw?.maxConditions ?? 1), 10) || 1));
    const maxActions = Math.max(1, Math.min(50, parseInt(String(raw?.maxActions ?? 1), 10) || 1));
    const displayColumns = parseInt(String(raw?.displayColumns ?? 1), 10) === 2 ? 2 : 1;
    return { maxConditions, maxActions, displayColumns };
  }
  function normalizeConfig(raw) {
    return {
      strings: {},
      ...raw,
      panelConfig: normalizePanelConfig(raw?.panelConfig),
      defaultVersion: raw?.defaultVersion ?? "1.0.0",
      recurrences: raw?.recurrences ?? ["hourly", "twicedaily", "daily", "weekly"],
      operators: raw?.operators ?? ["eq"],
      operatorLabels: raw?.operatorLabels ?? {},
      actionTypes: raw?.actionTypes ?? ["send_sms"],
      actionTypeLabels: raw?.actionTypeLabels ?? {},
      recurrenceLabels: raw?.recurrenceLabels ?? {},
      suggestedFields: normalizeSuggestedConditionFields(raw?.suggestedFields),
      smsTemplates: normalizeSmsTemplates(raw?.smsTemplates),
      events: raw?.events ?? [],
      useJalaliCalendar: Boolean(raw?.useJalaliCalendar),
      jalaliMonthNames: normalizeJalaliMonthNames(raw?.jalaliMonthNames)
    };
  }
  function normalizeJalaliMonthNames(raw) {
    if (!Array.isArray(raw) || raw.length !== 12) return void 0;
    const names = raw.map((m) => String(m ?? "").trim());
    return names.every((n) => n !== "") ? names : void 0;
  }
  function normalizeSmsTemplates(raw) {
    if (!Array.isArray(raw)) return [];
    const out = [];
    for (const item of raw) {
      if (!item || typeof item !== "object") continue;
      const row = item;
      const id = typeof row.id === "string" ? row.id.trim() : "";
      if (id === "") continue;
      out.push({
        id,
        label: typeof row.label === "string" && row.label.trim() !== "" ? row.label.trim() : id,
        body: typeof row.body === "string" ? row.body : ""
      });
    }
    return out;
  }
  function t(cfg, key, fallback) {
    return stringT(cfg, key, fallback);
  }
  function useCfg() {
    return useContext(CfgContext);
  }
  function usePanelConfig() {
    const cfg = useCfg();
    return cfg.panelConfig ?? normalizePanelConfig(void 0);
  }
  function gridColsClass(baseClass, columns) {
    const cols = columns === 2 ? 2 : 1;
    return `${baseClass} ${baseClass}--cols-${String(cols)}`;
  }
  function openRuleModalAria(cfg, ruleNum, title) {
    return stringT(cfg, "openRuleModal", "Edit rule %s").replace("%s", `#${String(ruleNum)}: ${title}`);
  }
  function removeCollapsedAria(cfg, ruleNum, ruleTitle) {
    return stringT(cfg, "removeRuleCollapsedAria", "Remove %s permanently from this list").replace(
      "%s",
      `rule #${String(ruleNum)}: ${ruleTitle}`
    );
  }
  function openConditionModalAria(cfg, num, summary) {
    return stringT(cfg, "openConditionModal", "Edit condition %s").replace("%s", `#${String(num)}: ${summary}`);
  }
  function openActionModalAria(cfg, num, summary) {
    return stringT(cfg, "openActionModal", "Edit action %s").replace("%s", `#${String(num)}: ${summary}`);
  }
  function removeConditionItemAria(cfg, num, summary) {
    return stringT(cfg, "removeConditionAria", "Remove condition %s from this rule").replace("%s", `#${String(num)}: ${summary}`);
  }
  function removeActionItemAria(cfg, num, summary) {
    return stringT(cfg, "removeActionAria", "Remove action %s from this rule").replace("%s", `#${String(num)}: ${summary}`);
  }
  function AdvancedRuleCard({ ruleIndex, snapshot, onRemove }) {
    const cfg = useCfg();
    const [isOpen, setIsOpen] = useState(false);
    const [confirmRemove, setConfirmRemove] = useState(false);
    const ruleId = typeof snapshot.id === "string" ? snapshot.id : `#${ruleIndex + 1}`;
    const ruleNumHuman = ruleIndex + 1;
    const summaryForAria = typeof snapshot.id === "string" ? snapshot.id : `Rule ${ruleNumHuman}`;
    const openAria = openRuleModalAria(cfg, ruleNumHuman, summaryForAria);
    const removeAria = removeCollapsedAria(cfg, ruleNumHuman, summaryForAria);
    const enabled = typeof snapshot.enabled === "boolean" ? snapshot.enabled : true;
    const priority = typeof snapshot.priority === "number" ? snapshot.priority : parseInt(String(snapshot.priority ?? 10), 10) || 10;
    const label = typeof snapshot.label === "string" ? snapshot.label : "";
    return /* @__PURE__ */ element.createElement(element.Fragment, null, /* @__PURE__ */ element.createElement("article", { className: "gro-rule-row gro-rule-row--advanced" }, /* @__PURE__ */ element.createElement("button", { type: "button", className: "gro-rule-row__open", "aria-label": openAria, onClick: () => setIsOpen(true) }, /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__cell gro-rule-row__cell--label" }, /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__label" }, t(cfg, "labelCol", "Label")), /* @__PURE__ */ element.createElement("strong", null, label.trim() || "—")), /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__cell gro-rule-row__cell--priority" }, /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__label" }, t(cfg, "priorityLabel", "Priority")), /* @__PURE__ */ element.createElement("strong", null, priority)), /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__cell gro-rule-row__cell--status" }, /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__label" }, t(cfg, "statusLabel", "Status")), /* @__PURE__ */ element.createElement("span", { className: "gro-badge gro-badge--warn" }, t(cfg, "advancedBadge", "Advanced")), /* @__PURE__ */ element.createElement(
      "span",
      {
        className: enabled ? "gro-rule-card__status-badge gro-rule-card__status-badge--on" : "gro-rule-card__status-badge gro-rule-card__status-badge--off"
      },
      enabled ? t(cfg, "enabledLabel", "Enabled") : t(cfg, "disabledLabel", "Disabled")
    )), /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__edit-hint", "aria-hidden": true }, /* @__PURE__ */ element.createElement(GroIcon, { name: "edit" }))), /* @__PURE__ */ element.createElement(
      GroButton,
      {
        icon: "delete",
        variant: "danger",
        size: "sm",
        iconOnly: true,
        className: "gro-rule-row__delete",
        "aria-label": removeAria,
        onClick: () => setConfirmRemove(true)
      }
    )), /* @__PURE__ */ element.createElement(
      Modal,
      {
        title: String(ruleId),
        isOpen,
        onClose: () => setIsOpen(false)
      },
      element.createElement(
        window.wp.components.Notice,
        { status: "warning", isDismissible: false },
        t(
          cfg,
          "advancedDesc",
          "This rule uses triggers or nested conditions this editor cannot change. It stays read-only and runs as stored."
        )
      ),
      /* @__PURE__ */ element.createElement("div", { className: "gro-editor-modal__toolbar" }, /* @__PURE__ */ element.createElement(
        GroButton,
        {
          size: "sm",
          variant: "danger",
          icon: "delete",
          onClick: () => setConfirmRemove(true)
        },
        t(cfg, "removeRule", "Remove")
      ))
    ), /* @__PURE__ */ element.createElement(
      ConfirmDialog,
      {
        isOpen: confirmRemove,
        message: t(cfg, "confirmRemoveAdv", "Remove this rule from the document?"),
        confirmLabel: t(cfg, "removeRule", "Remove"),
        onConfirm: () => {
          setConfirmRemove(false);
          setIsOpen(false);
          onRemove();
        },
        onCancel: () => setConfirmRemove(false)
      }
    ));
  }
  function monthNamesFor(cfg) {
    const fromCfg = cfg.jalaliMonthNames;
    if (Array.isArray(fromCfg) && fromCfg.length === 12) {
      return fromCfg;
    }
    return [
      "فروردین",
      "اردیبهشت",
      "خرداد",
      "تیر",
      "مرداد",
      "شهریور",
      "مهر",
      "آبان",
      "آذر",
      "دی",
      "بهمن",
      "اسفند"
    ];
  }
  function clampJalaliToMin(j, minIso) {
    const iso = gregorianIsoFromJalali(j.jy, j.jm, j.jd);
    const clamped = clampGregorianIsoToMin(iso, minIso);
    return jalaliFromGregorianIso(clamped);
  }
  function JalaliDateInput({ value, min, onChange, className = "" }) {
    const cfg = useCfg();
    const months = monthNamesFor(cfg);
    const safeValue = useMemo(() => clampGregorianIsoToMin(value || min, min), [value, min]);
    const selected = useMemo(() => clampJalaliToMin(jalaliFromGregorianIso(safeValue), min), [safeValue, min]);
    const years = useMemo(() => jalaliYearRange(min), [min]);
    const daysInMonth = useMemo(
      () => jalaliMonthLength(selected.jy, selected.jm),
      [selected.jy, selected.jm]
    );
    const emit = useCallback(
      (next) => {
        const clamped = clampJalaliToMin(next, min);
        const iso = gregorianIsoFromJalali(clamped.jy, clamped.jm, clamped.jd);
        onChange(iso);
      },
      [min, onChange]
    );
    const minJ = useMemo(() => jalaliFromGregorianIso(min), [min]);
    const dayOptions = useMemo(() => {
      const opts = [];
      for (let d = 1; d <= daysInMonth; d++) {
        const iso = gregorianIsoFromJalali(selected.jy, selected.jm, d);
        if (compareGregorianIso(iso, min) >= 0) opts.push(d);
      }
      return opts.length ? opts : [Math.min(selected.jd, daysInMonth)];
    }, [daysInMonth, min, selected.jm, selected.jy, selected.jd]);
    const summary = formatJalaliLong(safeValue, months, true);
    return /* @__PURE__ */ element.createElement("div", { className: `gro-jalali-date${className ? ` ${className}` : ""}`, dir: "rtl", lang: "fa" }, /* @__PURE__ */ element.createElement("div", { className: "gro-jalali-date__fields" }, /* @__PURE__ */ element.createElement("label", { className: "gro-jalali-date__field" }, /* @__PURE__ */ element.createElement("span", { className: "gro-jalali-date__label" }, t(cfg, "jalaliDay", "Day")), /* @__PURE__ */ element.createElement(
      "select",
      {
        className: "gro-jalali-date__select",
        value: String(Math.min(selected.jd, daysInMonth)),
        "aria-label": t(cfg, "jalaliDay", "Day"),
        onChange: (ev) => emit({ ...selected, jd: parseInt(ev.target.value, 10) || 1 })
      },
      dayOptions.map((d) => /* @__PURE__ */ element.createElement("option", { key: d, value: d }, toPersianDigits(d)))
    )), /* @__PURE__ */ element.createElement("label", { className: "gro-jalali-date__field" }, /* @__PURE__ */ element.createElement("span", { className: "gro-jalali-date__label" }, t(cfg, "jalaliMonth", "Month")), /* @__PURE__ */ element.createElement(
      "select",
      {
        className: "gro-jalali-date__select",
        value: String(selected.jm),
        "aria-label": t(cfg, "jalaliMonth", "Month"),
        onChange: (ev) => {
          const jm = parseInt(ev.target.value, 10) || 1;
          const maxDay = jalaliMonthLength(selected.jy, jm);
          emit({ ...selected, jm, jd: Math.min(selected.jd, maxDay) });
        }
      },
      months.map((name, i) => {
        const jm = i + 1;
        if (selected.jy > minJ.jy || selected.jy === minJ.jy && jm >= minJ.jm) {
          return /* @__PURE__ */ element.createElement("option", { key: jm, value: jm }, name);
        }
        return null;
      })
    )), /* @__PURE__ */ element.createElement("label", { className: "gro-jalali-date__field" }, /* @__PURE__ */ element.createElement("span", { className: "gro-jalali-date__label" }, t(cfg, "jalaliYear", "Year")), /* @__PURE__ */ element.createElement(
      "select",
      {
        className: "gro-jalali-date__select",
        value: String(selected.jy),
        "aria-label": t(cfg, "jalaliYear", "Year"),
        onChange: (ev) => {
          const jy = parseInt(ev.target.value, 10) || selected.jy;
          const maxDay = jalaliMonthLength(jy, selected.jm);
          emit({ ...selected, jy, jd: Math.min(selected.jd, maxDay) });
        }
      },
      years.map((y) => /* @__PURE__ */ element.createElement("option", { key: y, value: y }, toPersianDigits(y)))
    ))), /* @__PURE__ */ element.createElement("p", { className: "gro-jalali-date__summary", "aria-live": "polite" }, summary));
  }
  function DateInput({ value, min, onChange, className = "" }) {
    const cfg = useCfg();
    if (cfg.useJalaliCalendar) {
      return /* @__PURE__ */ element.createElement(JalaliDateInput, { value, min, onChange, className });
    }
    return /* @__PURE__ */ element.createElement(
      "input",
      {
        type: "date",
        className: className || "gro-action-delay__date",
        min,
        value,
        onChange: (ev) => onChange(ev.target.value)
      }
    );
  }
  function syncSchedule(schedule, onChange) {
    onChange(schedule, computeDelaySeconds(schedule, /* @__PURE__ */ new Date()));
  }
  function ActionDelayField({ schedule, onChange }) {
    const cfg = useCfg();
    const minDate = todayIsoDate();
    const preview = useMemo(() => formatDelayPreview(schedule, cfg), [schedule, cfg]);
    const setActive = (active) => {
      if (!active) {
        syncSchedule({ ...defaultDelaySchedule(), active: false }, onChange);
        return;
      }
      syncSchedule({ ...schedule, active: true }, onChange);
    };
    const patch = (partial) => {
      syncSchedule({ ...schedule, ...partial }, onChange);
    };
    return /* @__PURE__ */ element.createElement("div", { className: "gro-action-delay", role: "group", "aria-label": t(cfg, "delayLabel", "Delay") }, /* @__PURE__ */ element.createElement("div", { className: "gro-action-delay__mode" }, /* @__PURE__ */ element.createElement(
      "button",
      {
        type: "button",
        className: `gro-action-delay__mode-btn${!schedule.active ? " is-active" : ""}`,
        "aria-pressed": !schedule.active,
        onClick: () => setActive(false)
      },
      /* @__PURE__ */ element.createElement("span", { className: "gro-action-delay__mode-title" }, t(cfg, "delayImmediateShort", "Immediate")),
      /* @__PURE__ */ element.createElement("span", { className: "gro-action-delay__mode-desc" }, t(cfg, "delayModeImmediateHint", "When the rule matches"))
    ), /* @__PURE__ */ element.createElement(
      "button",
      {
        type: "button",
        className: `gro-action-delay__mode-btn${schedule.active ? " is-active" : ""}`,
        "aria-pressed": schedule.active,
        onClick: () => setActive(true)
      },
      /* @__PURE__ */ element.createElement("span", { className: "gro-action-delay__mode-title" }, t(cfg, "delayModeSchedule", "Schedule")),
      /* @__PURE__ */ element.createElement("span", { className: "gro-action-delay__mode-desc" }, t(cfg, "delayModeScheduleHint", "Pick date and time after now"))
    )), schedule.active ? /* @__PURE__ */ element.createElement(element.Fragment, null, /* @__PURE__ */ element.createElement("div", { className: "gro-action-delay__panels" }, /* @__PURE__ */ element.createElement("section", { className: "gro-action-delay__panel", "aria-labelledby": "gro-delay-date-heading" }, /* @__PURE__ */ element.createElement("header", { className: "gro-action-delay__panel-head", id: "gro-delay-date-heading" }, /* @__PURE__ */ element.createElement("span", null, t(cfg, "delayDateLegend", "Date"))), /* @__PURE__ */ element.createElement("div", { className: "gro-action-delay__chips", role: "group", "aria-label": t(cfg, "delayDateLegend", "Date") }, /* @__PURE__ */ element.createElement(
      DelayChip,
      {
        selected: schedule.dateMode === "today",
        onClick: () => patch({ dateMode: "today" })
      },
      t(cfg, "delayDateToday", "Today")
    ), /* @__PURE__ */ element.createElement(
      DelayChip,
      {
        selected: schedule.dateMode === "days",
        onClick: () => patch({ dateMode: "days", days: schedule.days > 0 ? schedule.days : 1 })
      },
      t(cfg, "delayDateInDays", "In"),
      " … ",
      t(cfg, "delayDateDaysLabel", "days")
    ), /* @__PURE__ */ element.createElement(
      DelayChip,
      {
        selected: schedule.dateMode === "custom",
        onClick: () => patch({
          dateMode: "custom",
          customDate: schedule.customDate || minDate
        })
      },
      t(cfg, "delayDateOn", "On"),
      " …"
    )), schedule.dateMode === "days" ? element.createElement(window.wp.components.TextControl, {
      label: `${t(cfg, "delayDateInDays", "In")} ${t(cfg, "delayDateDaysLabel", "days")}`,
      type: "number",
      min: 1,
      max: 365,
      value: String(schedule.days),
      onChange: (value) => patch({ dateMode: "days", days: parseInt(value, 10) || 1 }),
      __nextHasNoMarginBottom: true,
      __next40pxDefaultSize: true
    }) : null, schedule.dateMode === "custom" ? /* @__PURE__ */ element.createElement("div", { className: "gro-action-delay__detail" }, /* @__PURE__ */ element.createElement("label", { className: "gro-action-delay__detail-label" }, /* @__PURE__ */ element.createElement("span", null, t(cfg, "delayDateOn", "On")), /* @__PURE__ */ element.createElement(
      DateInput,
      {
        className: "gro-action-delay__date",
        min: minDate,
        value: schedule.customDate || minDate,
        onChange: (iso) => patch({ dateMode: "custom", customDate: iso })
      }
    ))) : null, /* @__PURE__ */ element.createElement("div", { className: "gro-action-delay__detail gro-action-delay__detail--clock" }, /* @__PURE__ */ element.createElement("label", { className: "gro-action-delay__detail-label gro-action-delay__detail-label--stack" }, /* @__PURE__ */ element.createElement("span", null, t(cfg, "delayClockOnDay", "At time on that day")), /* @__PURE__ */ element.createElement(
      "input",
      {
        type: "time",
        className: "gro-action-delay__time",
        value: schedule.customTime || "",
        onChange: (ev) => {
          const v = ev.target.value;
          if (v) {
            patch({ timeMode: "custom", customTime: v });
          } else {
            patch({ timeMode: "none", customTime: "" });
          }
        }
      }
    )), /* @__PURE__ */ element.createElement("p", { className: "gro-action-delay__hint description" }, t(cfg, "delayClockHint", "Combine with the date above — e.g. in 1 day at 11:00")))), /* @__PURE__ */ element.createElement("section", { className: "gro-action-delay__panel", "aria-labelledby": "gro-delay-time-heading" }, /* @__PURE__ */ element.createElement("header", { className: "gro-action-delay__panel-head", id: "gro-delay-time-heading" }, /* @__PURE__ */ element.createElement("span", null, t(cfg, "delayTimeLegend", "Time"))), /* @__PURE__ */ element.createElement("p", { className: "gro-action-delay__panel-note description" }, t(cfg, "delayExtraTimeHint", "Optional extra wait after the date and clock time above.")), /* @__PURE__ */ element.createElement("div", { className: "gro-action-delay__chips", role: "group", "aria-label": t(cfg, "delayTimeLegend", "Time") }, /* @__PURE__ */ element.createElement(
      DelayChip,
      {
        selected: schedule.timeMode === "none",
        onClick: () => patch({ timeMode: "none", relativeAmount: 0 })
      },
      t(cfg, "delayTimeNoneShort", "No extra delay")
    ), /* @__PURE__ */ element.createElement(
      DelayChip,
      {
        selected: schedule.timeMode === "seconds",
        onClick: () => patch({ timeMode: "seconds", relativeAmount: schedule.relativeAmount > 0 ? schedule.relativeAmount : 30 })
      },
      "+ ",
      t(cfg, "delayTimeSeconds", "seconds")
    ), /* @__PURE__ */ element.createElement(
      DelayChip,
      {
        selected: schedule.timeMode === "minutes",
        onClick: () => patch({ timeMode: "minutes", relativeAmount: schedule.relativeAmount > 0 ? schedule.relativeAmount : 5 })
      },
      "+ ",
      t(cfg, "delayTimeMinutes", "minutes")
    ), /* @__PURE__ */ element.createElement(
      DelayChip,
      {
        selected: schedule.timeMode === "hours",
        onClick: () => patch({ timeMode: "hours", relativeAmount: schedule.relativeAmount > 0 ? schedule.relativeAmount : 1 })
      },
      "+ ",
      t(cfg, "delayTimeHours", "hours")
    )), schedule.timeMode !== "none" && schedule.timeMode !== "custom" ? /* @__PURE__ */ element.createElement("div", { className: "gro-action-delay__detail" }, /* @__PURE__ */ element.createElement(
      RelativeTimeDetail,
      {
        mode: schedule.timeMode,
        amount: schedule.relativeAmount,
        cfg,
        onAmount: (amount) => patch({ timeMode: schedule.timeMode, relativeAmount: amount })
      }
    )) : null)), /* @__PURE__ */ element.createElement("div", { className: "gro-action-delay__preview", role: "status" }, /* @__PURE__ */ element.createElement("span", { className: "gro-action-delay__preview-icon", "aria-hidden": true }), /* @__PURE__ */ element.createElement("span", { className: "gro-action-delay__preview-body" }, /* @__PURE__ */ element.createElement("span", { className: "gro-action-delay__preview-label" }, t(cfg, "delayPreviewLabel", "Preview:")), /* @__PURE__ */ element.createElement("span", { className: "gro-action-delay__preview-text" }, preview)))) : null);
  }
  function DelayChip({
    selected,
    onClick,
    children
  }) {
    const Button = window.wp.components.Button;
    return /* @__PURE__ */ element.createElement(
      Button,
      {
        variant: "secondary",
        className: `gro-delay-chip${selected ? " is-selected" : ""}`,
        "aria-pressed": selected,
        onClick
      },
      children
    );
  }
  function RelativeTimeDetail({
    mode,
    amount,
    cfg,
    onAmount
  }) {
    const unit = mode === "hours" ? t(cfg, "delayTimeHours", "hours") : mode === "minutes" ? t(cfg, "delayTimeMinutes", "minutes") : t(cfg, "delayTimeSeconds", "seconds");
    const max = mode === "hours" ? 168 : mode === "minutes" ? 10080 : 86400;
    return element.createElement(window.wp.components.TextControl, {
      label: `+ ${unit}`,
      type: "number",
      min: 1,
      max,
      value: String(amount > 0 ? amount : 1),
      onChange: (value) => onAmount(parseInt(value, 10) || 1),
      __nextHasNoMarginBottom: true,
      __next40pxDefaultSize: true
    });
  }
  function usesSmsTemplate(type) {
    return type === "send_sms" || type === "send_discount";
  }
  function templateLabel(templates, templateId) {
    const id = templateId.trim();
    if (id === "") return "—";
    const hit = templates.find((x) => x.id === id);
    return hit ? hit.label : id;
  }
  function openPatternsTab() {
    document.getElementById("gro-rules-tab-patterns")?.click();
  }
  function ActionItem({ ruleIndex, idx, row: a, editable: e, onChange }) {
    const cfg = useCfg();
    const [isOpen, setIsOpen] = useState(false);
    const [draft, setDraft] = useState(null);
    const [customTemplateId, setCustomTemplateId] = useState(false);
    const at = cfg.actionTypes ?? ["log"];
    const smsTemplates = cfg.smsTemplates ?? [];
    const commitRow = useCallback(
      (row) => {
        onChange({ ...e, actions: e.actions.map((x, i) => i === idx ? row : x) });
      },
      [e, idx, onChange]
    );
    const openModal = () => {
      setDraft({ ...a });
      const known = smsTemplates.some((x) => x.id === (a.template_id || "").trim());
      setCustomTemplateId((a.template_id || "").trim() !== "" && !known);
      setIsOpen(true);
    };
    const closeModal = useCallback(() => {
      setIsOpen(false);
      setDraft(null);
      setCustomTemplateId(false);
    }, []);
    const applyAndClose = useCallback(() => {
      if (draft) commitRow(draft);
      closeModal();
    }, [closeModal, commitRow, draft]);
    const typeLabel = slugLabel(cfg.actionTypeLabels, at.includes(a.type) ? a.type : at[0]);
    const templateShown = templateLabel(smsTemplates, a.template_id);
    const delayShown = formatDelaySummary(a.delaySchedule ?? defaultDelaySchedule(), a.delay_seconds, cfg);
    const numHuman = idx + 1;
    const openAria = openActionModalAria(cfg, numHuman, `${typeLabel} · ${templateShown}`);
    const removeAria = removeActionItemAria(cfg, numHuman, typeLabel);
    const renderTemplateField = (row, onRowChange) => {
      const showPicker = usesSmsTemplate(row.type) && smsTemplates.length > 0;
      const selected = smsTemplates.find((x) => x.id === row.template_id);
      const previewBody = selected?.body ?? "";
      const TextControl = window.wp.components.TextControl;
      const SelectControl = window.wp.components.SelectControl;
      const Button = window.wp.components.Button;
      if (!showPicker) {
        return /* @__PURE__ */ element.createElement(
          TextControl,
          {
            label: t(cfg, "templateId", "template_id"),
            value: row.template_id,
            onChange: (template_id) => onRowChange({ ...row, template_id }),
            __nextHasNoMarginBottom: true,
            __next40pxDefaultSize: true
          }
        );
      }
      if (customTemplateId) {
        return /* @__PURE__ */ element.createElement(element.Fragment, null, /* @__PURE__ */ element.createElement(
          TextControl,
          {
            label: t(cfg, "templateId", "template_id"),
            value: row.template_id,
            placeholder: t(cfg, "templateCustom", "Custom template ID…"),
            onChange: (template_id) => onRowChange({ ...row, template_id }),
            __nextHasNoMarginBottom: true,
            __next40pxDefaultSize: true
          }
        ), /* @__PURE__ */ element.createElement(Button, { variant: "tertiary", onClick: () => setCustomTemplateId(false) }, t(cfg, "templateSelect", "SMS template")));
      }
      const orphanId = row.template_id.trim();
      const orphanInList = orphanId !== "" && !smsTemplates.some((x) => x.id === orphanId);
      return /* @__PURE__ */ element.createElement(element.Fragment, null, /* @__PURE__ */ element.createElement(
        SelectControl,
        {
          label: t(cfg, "templateSelect", "SMS template"),
          value: orphanInList ? orphanId : row.template_id,
          options: [
            { label: t(cfg, "valueSelectPlaceholder", "Select a value…"), value: "" },
            ...smsTemplates.map((tpl) => ({ label: `${tpl.label} (${tpl.id})`, value: tpl.id })),
            ...orphanInList ? [{ label: orphanId, value: orphanId }] : [],
            { label: t(cfg, "templateCustom", "Custom template ID…"), value: "__custom__" }
          ],
          onChange: (value) => {
            if (value === "__custom__") {
              setCustomTemplateId(true);
              onRowChange({ ...row, template_id: "" });
              return;
            }
            onRowChange({ ...row, template_id: value });
          },
          __nextHasNoMarginBottom: true,
          __next40pxDefaultSize: true
        }
      ), previewBody ? /* @__PURE__ */ element.createElement("div", { className: "gro-action-preview" }, /* @__PURE__ */ element.createElement("span", { className: "gro-action-preview__label" }, t(cfg, "templatePreview", "Preview:")), /* @__PURE__ */ element.createElement("p", { className: "gro-action-preview__text" }, previewBody)) : null, /* @__PURE__ */ element.createElement(Button, { className: "gro-action-form__link", variant: "link", onClick: openPatternsTab }, t(cfg, "templateConfigHint", "Edit message text under Message patterns.")));
    };
    const renderForm = (row, onRowChange) => /* @__PURE__ */ element.createElement("div", { className: "gro-action-form" }, /* @__PURE__ */ element.createElement("section", { className: "gro-action-form__section" }, /* @__PURE__ */ element.createElement("h3", { className: "gro-action-form__heading" }, t(cfg, "actionsHeading", "Actions")), /* @__PURE__ */ element.createElement("div", { className: "gro-action-form__body" }, element.createElement(window.wp.components.SelectControl, {
      label: t(cfg, "actionType", "Type"),
      value: at.includes(row.type) ? row.type : at[0],
      options: at.map((x) => ({ label: slugLabel(cfg.actionTypeLabels, x), value: x })),
      onChange: (type) => onRowChange({ ...row, type }),
      __nextHasNoMarginBottom: true,
      __next40pxDefaultSize: true
    }), renderTemplateField(row, onRowChange))), /* @__PURE__ */ element.createElement("section", { className: "gro-action-form__section" }, /* @__PURE__ */ element.createElement("h3", { className: "gro-action-form__heading" }, t(cfg, "delayLabel", "Delay")), /* @__PURE__ */ element.createElement("div", { className: "gro-action-form__body" }, /* @__PURE__ */ element.createElement(
      ActionDelayField,
      {
        schedule: row.delaySchedule ?? defaultDelaySchedule(),
        onChange: (schedule, delaySeconds) => onRowChange({ ...row, delaySchedule: schedule, delay_seconds: delaySeconds })
      }
    ))), /* @__PURE__ */ element.createElement("section", { className: "gro-action-form__section" }, /* @__PURE__ */ element.createElement("h3", { className: "gro-action-form__heading" }, t(cfg, "paramsJson", "params (JSON object)")), /* @__PURE__ */ element.createElement("div", { className: "gro-action-form__body" }, element.createElement(window.wp.components.TextareaControl, {
      label: t(cfg, "paramsJson", "params (JSON object)"),
      hideLabelFromVision: true,
      rows: 5,
      value: row.paramsJson,
      onChange: (paramsJson) => onRowChange({ ...row, paramsJson }),
      __nextHasNoMarginBottom: true
    }))));
    return /* @__PURE__ */ element.createElement(element.Fragment, null, /* @__PURE__ */ element.createElement("article", { className: "gro-item-card gro-item-card--closed" }, /* @__PURE__ */ element.createElement("button", { type: "button", className: "gro-item-card__open", "aria-label": openAria, onClick: openModal }, /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__index", "aria-hidden": true }, "#", numHuman), /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__field" }, typeLabel), /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__meta" }, usesSmsTemplate(a.type) ? /* @__PURE__ */ element.createElement(element.Fragment, null, /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__meta-key" }, t(cfg, "templateSelect", "SMS template")), " ", /* @__PURE__ */ element.createElement("strong", null, templateShown)) : /* @__PURE__ */ element.createElement("code", null, (a.template_id || "").trim() || "—"), /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__sep", "aria-hidden": true }, " · "), /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__meta-key" }, t(cfg, "delayLabel", "Delay")), " ", /* @__PURE__ */ element.createElement("strong", null, delayShown)), /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__edit-hint", "aria-hidden": true }, /* @__PURE__ */ element.createElement(GroIcon, { name: "edit" }))), /* @__PURE__ */ element.createElement(
      GroButton,
      {
        icon: "delete",
        variant: "danger",
        size: "sm",
        iconOnly: true,
        className: "gro-item-card__delete",
        "aria-label": removeAria,
        onClick: () => {
          const next = e.actions.filter((_, i) => i !== idx);
          onChange({
            ...e,
            actions: next.length ? next : [{ type: at[0], template_id: "", delay_seconds: 0, delaySchedule: defaultDelaySchedule(), paramsJson: "{}" }]
          });
        }
      }
    )), /* @__PURE__ */ element.createElement(
      Modal,
      {
        title: `${t(cfg, "actionsHeading", "Actions")} #${numHuman}`,
        isOpen,
        onClose: applyAndClose
      },
      draft ? renderForm(draft, setDraft) : null
    ));
  }
  function ActionsBlock({ ruleIndex, editable: e, onChange }) {
    const cfg = useCfg();
    const panel = usePanelConfig();
    const at = cfg.actionTypes ?? ["log"];
    const canAdd = e.actions.length < panel.maxActions;
    return /* @__PURE__ */ element.createElement("section", { className: "gro-action-form__section" }, /* @__PURE__ */ element.createElement("h3", { className: "gro-action-form__heading" }, t(cfg, "actionsHeading", "Actions")), /* @__PURE__ */ element.createElement("div", { className: "gro-action-form__body" }, /* @__PURE__ */ element.createElement("div", { className: gridColsClass("gro-items-grid", panel.displayColumns), role: "list" }, e.actions.map((a, idx) => /* @__PURE__ */ element.createElement("div", { key: idx, className: "gro-items-grid__item", role: "listitem" }, /* @__PURE__ */ element.createElement(ActionItem, { ruleIndex, idx, row: a, editable: e, onChange })))), canAdd ? /* @__PURE__ */ element.createElement(
      GroButton,
      {
        size: "sm",
        icon: "add",
        className: "gro-nested-list-add-button",
        onClick: () => onChange({
          ...e,
          actions: [
            ...e.actions,
            { type: at[0], template_id: "", delay_seconds: 0, delaySchedule: defaultDelaySchedule(), paramsJson: "{}" }
          ]
        })
      },
      t(cfg, "addAction", "Add action")
    ) : /* @__PURE__ */ element.createElement("p", { className: "description gro-list-limit-hint" }, t(cfg, "actionsLimitReached", "Maximum number of actions reached (%s).").replace("%s", String(panel.maxActions)))));
  }
  function ConditionValueField({ row, suggestedFields, onRowChange }) {
    const cfg = useCfg();
    const TextareaControl = window.wp.components.TextareaControl;
    const SelectControl = window.wp.components.SelectControl;
    const fieldDef = useMemo(() => findConditionField(suggestedFields, row.field), [row.field, suggestedFields]);
    const usePresets = fieldHasPresetValues(fieldDef, row.op);
    const matchedChoice = useMemo(
      () => usePresets ? findMatchingValueChoice(fieldDef, row.valueJson) : void 0,
      [fieldDef, row.valueJson, usePresets]
    );
    const [customMode, setCustomMode] = useState(() => usePresets && row.valueJson.trim() !== "" && !matchedChoice);
    useEffect(() => {
      if (!usePresets) {
        setCustomMode(false);
        return;
      }
      if (row.valueJson.trim() === "") {
        setCustomMode(false);
        return;
      }
      setCustomMode(!findMatchingValueChoice(fieldDef, row.valueJson));
    }, [fieldDef, row.field, row.op, row.valueJson, usePresets]);
    if (!opsNeedValue(row.op)) return null;
    if (!usePresets) {
      return /* @__PURE__ */ element.createElement(
        TextareaControl,
        {
          label: t(cfg, "valueCol", "Value (JSON)"),
          rows: 3,
          value: row.valueJson,
          placeholder: t(cfg, "valueJsonPlaceholder", 'Examples: true, false, 3, "text", []'),
          onChange: (valueJson) => onRowChange({ ...row, valueJson }),
          __nextHasNoMarginBottom: true
        }
      );
    }
    const choices = fieldDef?.values ?? [];
    const selectValue = customMode ? "__custom__" : matchedChoice ? valueJsonForChoice(matchedChoice) : "";
    return /* @__PURE__ */ element.createElement(element.Fragment, null, /* @__PURE__ */ element.createElement(
      SelectControl,
      {
        label: t(cfg, "valueColShort", "Value"),
        value: selectValue,
        options: [
          { label: t(cfg, "valueSelectPlaceholder", "Select a value…"), value: "", disabled: true },
          ...choices.map((choice) => ({ label: choice.label, value: valueJsonForChoice(choice) })),
          { label: t(cfg, "customValue", "Custom value (JSON)…"), value: "__custom__" }
        ],
        onChange: (value) => {
          if (value === "__custom__") {
            setCustomMode(true);
            return;
          }
          setCustomMode(false);
          onRowChange({ ...row, valueJson: value });
        },
        __nextHasNoMarginBottom: true,
        __next40pxDefaultSize: true
      }
    ), customMode ? /* @__PURE__ */ element.createElement(
      TextareaControl,
      {
        label: t(cfg, "customValue", "Custom value (JSON)…"),
        rows: 3,
        value: row.valueJson,
        placeholder: t(cfg, "valueJsonPlaceholder", 'Examples: true, false, 3, "text", []'),
        onChange: (valueJson) => onRowChange({ ...row, valueJson }),
        __nextHasNoMarginBottom: true
      }
    ) : null);
  }
  function applyFieldDefaults(row, suggestedFields, prevField) {
    const fieldDef = findConditionField(suggestedFields, row.field);
    if (!fieldHasPresetValues(fieldDef, row.op)) {
      if (prevField !== void 0 && prevField !== row.field) {
        return { ...row, valueJson: "" };
      }
      return row;
    }
    if (prevField !== void 0 && prevField !== row.field) {
      return { ...row, valueJson: defaultValueJsonForField(fieldDef) };
    }
    if (!row.valueJson.trim()) {
      return { ...row, valueJson: defaultValueJsonForField(fieldDef) };
    }
    if (!findMatchingValueChoice(fieldDef, row.valueJson)) {
      return row;
    }
    return row;
  }
  function ConditionItem({ ruleIndex, idx, row: c, editable: e, onChange }) {
    const cfg = useCfg();
    const [isOpen, setIsOpen] = useState(false);
    const [draft, setDraft] = useState(null);
    const suggested = cfg.suggestedFields ?? [];
    const suggestedNames = suggested.map((f) => f.name);
    const commitRow = useCallback(
      (row) => {
        onChange({ ...e, conditions: e.conditions.map((x, i) => i === idx ? row : x) });
      },
      [e, idx, onChange]
    );
    const openModal = () => {
      setDraft({ ...c });
      setIsOpen(true);
    };
    const closeModal = useCallback(() => {
      setIsOpen(false);
      setDraft(null);
    }, []);
    const applyAndClose = useCallback(() => {
      if (draft) commitRow(draft);
      closeModal();
    }, [closeModal, commitRow, draft]);
    const fieldShown = conditionFieldLabel(suggested, c.field) || (c.field || "").trim() || t(cfg, "fieldPlaceholder", "State field key");
    const opShown = slugLabel(cfg.operatorLabels, c.op);
    const valueShown = conditionValueLabel(suggested, c.field, c.valueJson);
    const numHuman = idx + 1;
    const summaryParts = [fieldShown, opShown, valueShown].filter(Boolean);
    const openAria = openConditionModalAria(cfg, numHuman, summaryParts.join(" · "));
    const removeAria = removeConditionItemAria(cfg, numHuman, fieldShown);
    const renderForm = (row, onRowChange) => {
      const match = suggestedNames.includes(row.field);
      const fieldSelValue = match ? row.field : "__custom__";
      const SelectControl = window.wp.components.SelectControl;
      const TextControl = window.wp.components.TextControl;
      return /* @__PURE__ */ element.createElement("div", { className: "gro-action-form" }, /* @__PURE__ */ element.createElement("div", { className: "gro-action-form__body gro-condition-item-fields" }, /* @__PURE__ */ element.createElement(
        SelectControl,
        {
          label: t(cfg, "fieldCol", "Field"),
          value: fieldSelValue,
          options: [
            { label: t(cfg, "customField", "Custom…"), value: "__custom__" },
            ...suggested.map((sf) => ({ label: sf.label, value: sf.name }))
          ],
          onChange: (value) => {
            const prevField = row.field;
            if (value === "__custom__") {
              onRowChange(applyFieldDefaults({ ...row, field: match ? "" : row.field }, suggested, prevField));
              return;
            }
            onRowChange(applyFieldDefaults({ ...row, field: value }, suggested, prevField));
          },
          __nextHasNoMarginBottom: true,
          __next40pxDefaultSize: true
        }
      ), fieldSelValue === "__custom__" ? /* @__PURE__ */ element.createElement(
        TextControl,
        {
          label: t(cfg, "fieldPlaceholder", "State field key"),
          value: match ? "" : row.field,
          onChange: (field) => onRowChange({ ...row, field }),
          __nextHasNoMarginBottom: true,
          __next40pxDefaultSize: true
        }
      ) : null, /* @__PURE__ */ element.createElement(
        SelectControl,
        {
          label: t(cfg, "opCol", "Operator"),
          value: row.op,
          options: operatorOptions(cfg, row.op).map((o) => ({ label: slugLabel(cfg.operatorLabels, o), value: o })),
          onChange: (op) => onRowChange(applyFieldDefaults({ ...row, op }, suggested, row.field)),
          __nextHasNoMarginBottom: true,
          __next40pxDefaultSize: true
        }
      ), row.op === "regex" ? /* @__PURE__ */ element.createElement(
        TextControl,
        {
          label: t(cfg, "patternCol", "Pattern (regex)"),
          value: row.pattern,
          placeholder: t(cfg, "regexPatternPlaceholder", "/^...$/"),
          onChange: (pattern) => onRowChange({ ...row, pattern }),
          __nextHasNoMarginBottom: true,
          __next40pxDefaultSize: true
        }
      ) : null, /* @__PURE__ */ element.createElement(ConditionValueField, { row, suggestedFields: suggested, onRowChange })));
    };
    return /* @__PURE__ */ element.createElement(element.Fragment, null, /* @__PURE__ */ element.createElement("article", { className: "gro-item-card gro-item-card--closed" }, /* @__PURE__ */ element.createElement("button", { type: "button", className: "gro-item-card__open", "aria-label": openAria, onClick: openModal }, /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__index", "aria-hidden": true }, "#", numHuman), /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__field gro-item-card__field--mono" }, fieldShown), /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__meta" }, /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__meta-key" }, t(cfg, "opCol", "Operator")), " ", /* @__PURE__ */ element.createElement("strong", null, opShown), valueShown ? /* @__PURE__ */ element.createElement(element.Fragment, null, /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__sep", "aria-hidden": true }, " · "), /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__meta-key" }, t(cfg, "valueColShort", "Value")), " ", /* @__PURE__ */ element.createElement("strong", null, valueShown)) : null), /* @__PURE__ */ element.createElement("span", { className: "gro-item-card__edit-hint", "aria-hidden": true }, /* @__PURE__ */ element.createElement(GroIcon, { name: "edit" }))), /* @__PURE__ */ element.createElement(
      GroButton,
      {
        icon: "delete",
        variant: "danger",
        size: "sm",
        iconOnly: true,
        className: "gro-item-card__delete",
        "aria-label": removeAria,
        onClick: () => {
          const next = e.conditions.filter((_, i) => i !== idx);
          onChange({
            ...e,
            conditions: next.length ? next : [{ field: "", op: "eq", valueJson: "", pattern: "" }]
          });
        }
      }
    )), /* @__PURE__ */ element.createElement(
      Modal,
      {
        title: `${t(cfg, "conditionsHeading", "Conditions")} #${numHuman}`,
        isOpen,
        onClose: applyAndClose
      },
      draft ? renderForm(draft, setDraft) : null
    ));
  }
  function ConditionsBlock({ ruleIndex, editable: e, onChange }) {
    const cfg = useCfg();
    const panel = usePanelConfig();
    const canAdd = e.conditions.length < panel.maxConditions;
    return /* @__PURE__ */ element.createElement("section", { className: "gro-action-form__section" }, /* @__PURE__ */ element.createElement("h3", { className: "gro-action-form__heading" }, t(cfg, "conditionsHeading", "Conditions (all must match)")), /* @__PURE__ */ element.createElement("div", { className: "gro-action-form__body" }, /* @__PURE__ */ element.createElement("div", { className: gridColsClass("gro-items-grid", panel.displayColumns), role: "list" }, e.conditions.map((c, idx) => /* @__PURE__ */ element.createElement("div", { key: idx, className: "gro-items-grid__item", role: "listitem" }, /* @__PURE__ */ element.createElement(ConditionItem, { ruleIndex, idx, row: c, editable: e, onChange })))), canAdd ? /* @__PURE__ */ element.createElement(
      GroButton,
      {
        size: "sm",
        icon: "add",
        className: "gro-nested-list-add-button",
        onClick: () => onChange({ ...e, conditions: [...e.conditions, { field: "", op: "eq", valueJson: "", pattern: "" }] })
      },
      t(cfg, "addCondition", "Add condition")
    ) : /* @__PURE__ */ element.createElement("p", { className: "description gro-list-limit-hint" }, t(cfg, "conditionsLimitReached", "Maximum number of conditions reached (%s).").replace(
      "%s",
      String(panel.maxConditions)
    ))));
  }
  function TriggersBlock({ ruleIndex, editable: te, onChange }) {
    const cfg = useCfg();
    const eventOpts = cfg.events ?? [];
    const extras = useMemo(() => te.events.filter((x) => !eventOpts.some((eo) => eo.value === x)), [te.events, eventOpts]);
    const knownEventValues = useMemo(() => eventOpts.map((eo) => eo.value), [eventOpts]);
    const selectedKnownCount = useMemo(
      () => te.events.filter((value) => knownEventValues.includes(value)).length,
      [te.events, knownEventValues]
    );
    const toggleEvent = (value, checked) => {
      let next = te.events.slice();
      if (checked && !next.includes(value)) next.push(value);
      if (!checked) next = next.filter((x) => x !== value);
      onChange({ ...te, events: next });
    };
    const selectAllEvents = () => {
      const merged = [...te.events];
      knownEventValues.forEach((value) => {
        if (!merged.includes(value)) merged.push(value);
      });
      onChange({ ...te, events: merged });
    };
    const clearKnownEvents = () => {
      onChange({ ...te, events: te.events.filter((value) => !knownEventValues.includes(value)) });
    };
    const syncCustomExtras = (customLine) => {
      const known = eventOpts.map((eo) => eo.value);
      const parts = customLine.split(",").map((s) => s.trim()).filter(Boolean);
      const fromCheckboxes = te.events.filter((x) => known.includes(x));
      const merged = [...fromCheckboxes];
      parts.forEach((p) => {
        if (!merged.includes(p)) merged.push(p);
      });
      onChange({ ...te, events: merged });
    };
    const TextControl = window.wp.components.TextControl;
    const SelectControl = window.wp.components.SelectControl;
    const modes = [
      ["none", t(cfg, "triggerNone", "Evaluate without event filter"), t(cfg, "triggerNoneHint", "Runs whenever rules are checked")],
      ["events", t(cfg, "triggerEvents", "On automation events"), t(cfg, "eventsSelectedHint", "Rule runs when any selected event fires.")],
      ["schedule", t(cfg, "triggerSchedule", "On a schedule (cron)"), t(cfg, "triggerScheduleHint", "WordPress cron recurrence")]
    ];
    return /* @__PURE__ */ element.createElement("section", { className: "gro-action-form__section" }, /* @__PURE__ */ element.createElement("h3", { className: "gro-action-form__heading" }, t(cfg, "triggerHeading", "When to run")), /* @__PURE__ */ element.createElement("div", { className: "gro-action-form__body" }, /* @__PURE__ */ element.createElement("div", { className: "gro-action-delay__mode gro-trigger-modes", role: "radiogroup", "aria-label": t(cfg, "triggerHeading", "When to run") }, modes.map(([value, title, hint]) => /* @__PURE__ */ element.createElement(
      "button",
      {
        key: value,
        type: "button",
        role: "radio",
        "aria-checked": te.mode === value,
        className: `gro-action-delay__mode-btn${te.mode === value ? " is-active" : ""}`,
        onClick: () => {
          const rec = cfg.recurrences?.[0] ?? "daily";
          onChange({
            ...te,
            mode: value,
            schedule: value === "schedule" && (!te.schedule.hook || te.mode !== "schedule") ? { hook: "", recurrence: te.schedule.recurrence || rec } : te.schedule
          });
        }
      },
      /* @__PURE__ */ element.createElement("span", { className: "gro-action-delay__mode-title" }, title),
      /* @__PURE__ */ element.createElement("span", { className: "gro-action-delay__mode-desc" }, hint)
    ))), te.mode === "events" ? /* @__PURE__ */ element.createElement(element.Fragment, null, /* @__PURE__ */ element.createElement("div", { className: "gro-events-picker" }, eventOpts.length > 0 ? /* @__PURE__ */ element.createElement("div", { className: "gro-events-picker__toolbar" }, /* @__PURE__ */ element.createElement("p", { className: "gro-events-picker__summary" }, /* @__PURE__ */ element.createElement("span", { className: "gro-events-picker__count" }, t(cfg, "eventsSelectedCount", "%1$s of %2$s events selected").replace("%1$s", String(selectedKnownCount)).replace("%2$s", String(eventOpts.length))), selectedKnownCount > 0 ? /* @__PURE__ */ element.createElement("span", { className: "gro-events-picker__hint" }, t(cfg, "eventsSelectedHint", "Rule runs when any selected event fires.")) : /* @__PURE__ */ element.createElement("span", { className: "gro-events-picker__hint gro-events-picker__hint--warn" }, t(cfg, "eventsNoneSelectedHint", "Select at least one event or add custom names below."))), /* @__PURE__ */ element.createElement("div", { className: "gro-events-picker__actions" }, /* @__PURE__ */ element.createElement(
      GroButton,
      {
        type: "button",
        variant: "ghost",
        size: "sm",
        disabled: selectedKnownCount === eventOpts.length,
        onClick: selectAllEvents
      },
      t(cfg, "selectAllEvents", "Select all")
    ), /* @__PURE__ */ element.createElement(
      GroButton,
      {
        type: "button",
        variant: "ghost",
        size: "sm",
        disabled: selectedKnownCount === 0,
        onClick: clearKnownEvents
      },
      t(cfg, "clearEvents", "Clear")
    ))) : null, eventOpts.length > 0 ? /* @__PURE__ */ element.createElement(
      "div",
      {
        className: "gro-events-grid",
        role: "group",
        "aria-label": t(cfg, "eventsGridLabel", "Automation events")
      },
      eventOpts.map((ev) => {
        const selected = te.events.includes(ev.value);
        const label = ev.label || ev.value;
        const showSlug = Boolean(ev.label && ev.label !== ev.value);
        return /* @__PURE__ */ element.createElement(
          "button",
          {
            key: ev.value,
            type: "button",
            className: `gro-event-option${selected ? " is-selected" : ""}`,
            "aria-pressed": selected,
            onClick: () => toggleEvent(ev.value, !selected)
          },
          /* @__PURE__ */ element.createElement("span", { className: "gro-event-option__label" }, label.replace(/_/g, "_​")),
          showSlug ? /* @__PURE__ */ element.createElement("span", { className: "gro-event-option__slug" }, ev.value.replace(/_/g, "_​")) : null
        );
      })
    ) : /* @__PURE__ */ element.createElement("p", { className: "gro-empty-state gro-events-picker__empty" }, t(cfg, "eventsCatalogEmpty", "No automation events are registered yet. Add custom event names below."))), /* @__PURE__ */ element.createElement(
      TextControl,
      {
        key: `ce-${extras.join("|")}-${ruleIndex}`,
        label: t(cfg, "customEventsLabel", "Additional event names (comma-separated)"),
        defaultValue: extras.join(", "),
        onBlur: (event) => syncCustomExtras(event.target.value),
        __nextHasNoMarginBottom: true,
        __next40pxDefaultSize: true
      }
    )) : null, te.mode === "schedule" ? /* @__PURE__ */ element.createElement("div", { className: "gro-schedule-fields" }, /* @__PURE__ */ element.createElement(
      TextControl,
      {
        label: t(cfg, "hookLabel", "Cron hook"),
        value: te.schedule.hook,
        onChange: (hook) => onChange({ ...te, schedule: { ...te.schedule, hook } }),
        __nextHasNoMarginBottom: true,
        __next40pxDefaultSize: true
      }
    ), /* @__PURE__ */ element.createElement(
      SelectControl,
      {
        label: t(cfg, "recurrenceLabel", "Recurrence"),
        value: te.schedule.recurrence,
        options: (cfg.recurrences ?? []).map((r) => ({ label: slugLabel(cfg.recurrenceLabels, r), value: r })),
        onChange: (recurrence) => onChange({ ...te, schedule: { ...te.schedule, recurrence } }),
        __nextHasNoMarginBottom: true,
        __next40pxDefaultSize: true
      }
    )) : null));
  }
  function SimpleRuleForm({
    ruleIndex,
    editable: e,
    onChange,
    onDuplicate,
    onMove,
    onRemove
  }) {
    const cfg = useCfg();
    const TextControl = window.wp.components.TextControl;
    const CheckboxControl = window.wp.components.CheckboxControl;
    return /* @__PURE__ */ element.createElement("div", { className: "gro-action-form" }, /* @__PURE__ */ element.createElement("section", { className: "gro-action-form__section" }, /* @__PURE__ */ element.createElement("h3", { className: "gro-action-form__heading" }, t(cfg, "ruleSection", "Rule")), /* @__PURE__ */ element.createElement("div", { className: "gro-action-form__body" }, /* @__PURE__ */ element.createElement(
      TextControl,
      {
        className: "gro-editor-modal__label",
        label: t(cfg, "placeholderLabel", "Label (optional)"),
        value: e.label,
        onChange: (label) => onChange({ ...e, label }),
        __nextHasNoMarginBottom: true,
        __next40pxDefaultSize: true
      }
    ), /* @__PURE__ */ element.createElement("div", { className: "gro-rule-fields" }, /* @__PURE__ */ element.createElement(
      TextControl,
      {
        label: t(cfg, "priorityLabel", "Priority"),
        type: "number",
        value: String(e.priority),
        onChange: (value) => onChange({ ...e, priority: parseInt(value, 10) || 10 }),
        __nextHasNoMarginBottom: true,
        __next40pxDefaultSize: true
      }
    ), /* @__PURE__ */ element.createElement(
      TextControl,
      {
        label: t(cfg, "ruleIdShort", "id"),
        value: e.id,
        onChange: (id) => onChange({ ...e, id }),
        __nextHasNoMarginBottom: true,
        __next40pxDefaultSize: true
      }
    )), /* @__PURE__ */ element.createElement(
      CheckboxControl,
      {
        className: "gro-editor-modal__enabled",
        label: t(cfg, "enabledLabel", "Enabled"),
        checked: e.enabled,
        onChange: (enabled) => onChange({ ...e, enabled }),
        __nextHasNoMarginBottom: true
      }
    ), /* @__PURE__ */ element.createElement("div", { className: "gro-editor-modal__toolbar" }, /* @__PURE__ */ element.createElement(GroButton, { size: "sm", icon: "duplicate", onClick: onDuplicate }, t(cfg, "dupRule", "Duplicate")), /* @__PURE__ */ element.createElement(GroButton, { size: "sm", icon: "up", onClick: () => onMove(-1) }, t(cfg, "up", "Move up")), /* @__PURE__ */ element.createElement(GroButton, { size: "sm", icon: "down", onClick: () => onMove(1) }, t(cfg, "down", "Move down")), /* @__PURE__ */ element.createElement(GroButton, { size: "sm", variant: "danger", icon: "delete", onClick: onRemove }, t(cfg, "removeRule", "Remove"))))), /* @__PURE__ */ element.createElement(TriggersBlock, { ruleIndex, editable: e.trigger, onChange: (trigger) => onChange({ ...e, trigger }) }), /* @__PURE__ */ element.createElement(ConditionsBlock, { ruleIndex, editable: e, onChange }), /* @__PURE__ */ element.createElement(ActionsBlock, { ruleIndex, editable: e, onChange }));
  }
  function cloneEditableRule(source) {
    return {
      ...source,
      trigger: {
        ...source.trigger,
        events: [...source.trigger.events],
        schedule: { ...source.trigger.schedule }
      },
      conditions: source.conditions.map((c) => ({ ...c })),
      actions: source.actions.map((a) => ({ ...a }))
    };
  }
  function SimpleRuleCard({
    ruleIndex,
    editable: e,
    onChange,
    onDuplicate,
    onMove,
    onRemove
  }) {
    const cfg = useCfg();
    const [isOpen, setIsOpen] = useState(false);
    const [confirmRemove, setConfirmRemove] = useState(false);
    const [draft, setDraft] = useState(null);
    const [localDirty, setLocalDirty] = useState(false);
    const displayLabel = e.label.trim() || t(cfg, "untitledRuleSummary", "Untitled rule");
    const ruleNumHuman = ruleIndex + 1;
    const openAria = openRuleModalAria(cfg, ruleNumHuman, displayLabel);
    const removeAria = removeCollapsedAria(cfg, ruleNumHuman, displayLabel);
    const modalTitle = draft?.label.trim() || displayLabel;
    const commitDraft = useCallback(
      (next) => {
        onChange(next);
      },
      [onChange]
    );
    const openModal = () => {
      setDraft(cloneEditableRule(e));
      setLocalDirty(false);
      setIsOpen(true);
    };
    const closeModal = useCallback(() => {
      if (localDirty && draft) commitDraft(draft);
      setIsOpen(false);
      setDraft(null);
      setLocalDirty(false);
    }, [commitDraft, draft, localDirty]);
    const handleChange = (next) => {
      setLocalDirty(true);
      setDraft(next);
    };
    return /* @__PURE__ */ element.createElement(element.Fragment, null, /* @__PURE__ */ element.createElement("article", { className: "gro-rule-row gro-rule-row--simple" }, /* @__PURE__ */ element.createElement("button", { type: "button", className: "gro-rule-row__open", "aria-label": openAria, onClick: openModal }, /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__cell gro-rule-row__cell--label" }, /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__label" }, t(cfg, "labelCol", "Label")), /* @__PURE__ */ element.createElement("strong", null, displayLabel)), /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__cell gro-rule-row__cell--priority" }, /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__label" }, t(cfg, "priorityLabel", "Priority")), /* @__PURE__ */ element.createElement("strong", null, e.priority)), /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__cell gro-rule-row__cell--status" }, /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__label" }, t(cfg, "statusLabel", "Status")), /* @__PURE__ */ element.createElement(
      "span",
      {
        className: e.enabled ? "gro-rule-card__status-badge gro-rule-card__status-badge--on" : "gro-rule-card__status-badge gro-rule-card__status-badge--off"
      },
      e.enabled ? t(cfg, "enabledLabel", "Enabled") : t(cfg, "disabledLabel", "Disabled")
    )), /* @__PURE__ */ element.createElement("span", { className: "gro-rule-row__edit-hint", "aria-hidden": true }, /* @__PURE__ */ element.createElement(GroIcon, { name: "edit" }))), /* @__PURE__ */ element.createElement(
      GroButton,
      {
        icon: "delete",
        variant: "danger",
        size: "sm",
        iconOnly: true,
        className: "gro-rule-row__delete",
        "aria-label": removeAria,
        onClick: () => setConfirmRemove(true)
      }
    )), /* @__PURE__ */ element.createElement(
      ConfirmDialog,
      {
        isOpen: confirmRemove,
        message: t(cfg, "confirmRemove", "Remove this rule?"),
        confirmLabel: t(cfg, "removeRule", "Remove"),
        onConfirm: () => {
          setConfirmRemove(false);
          onRemove();
        },
        onCancel: () => setConfirmRemove(false)
      }
    ), /* @__PURE__ */ element.createElement(
      Modal,
      {
        title: `${t(cfg, "ruleDetailsHeading", "Edit rule")} — ${modalTitle}`,
        isOpen,
        onClose: closeModal
      },
      draft ? /* @__PURE__ */ element.createElement(
        SimpleRuleForm,
        {
          ruleIndex,
          editable: draft,
          onChange: handleChange,
          onDuplicate: () => {
            onDuplicate();
            closeModal();
          },
          onMove,
          onRemove: () => {
            onRemove();
            closeModal();
          }
        }
      ) : null
    ));
  }
  function documentJson(version, uiRules, cfg) {
    const doc = serializeDocument(version, uiRules, cfg.defaultVersion ?? "1.0.0");
    return JSON.stringify(doc, null, 2);
  }
  function useRulesPersistence(cfg) {
    const [savedSnapshot, setSavedSnapshot] = useState(null);
    const [isSaving, setIsSaving] = useState(false);
    const [saveNotice, setSaveNotice] = useState(null);
    const markSaved = useCallback((json) => {
      setSavedSnapshot(json);
      setSaveNotice(null);
    }, []);
    const hasUnsavedChanges = useCallback(
      (version, uiRules) => {
        if (savedSnapshot === null) return false;
        return documentJson(version, uiRules, cfg) !== savedSnapshot;
      },
      [cfg, savedSnapshot]
    );
    const persistDocument = useCallback(
      async (version, uiRules) => {
        const json = documentJson(version, uiRules, cfg);
        const url = cfg.saveAjaxUrl;
        const action = cfg.saveAction ?? "gro_rules_save";
        const nonce = cfg.saveNonce;
        if (!url || !nonce) {
          return { ok: false, message: stringT(cfg, "saveFailed", "Rules could not be saved: %s").replace("%s", "Missing save configuration") };
        }
        setIsSaving(true);
        setSaveNotice(null);
        try {
          const body = new URLSearchParams();
          body.set("action", action);
          body.set("nonce", nonce);
          body.set("gro_rules_json", json);
          const res = await fetch(url, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: body.toString()
          });
          const payload = await res.json();
          if (!res.ok || !payload.success) {
            const errMsg = (payload.data && typeof payload.data === "object" && "message" in payload.data ? String(payload.data.message) : null) ?? payload.message ?? res.statusText;
            const msg = stringT(cfg, "saveFailed", "Rules could not be saved: %s").replace("%s", errMsg);
            setSaveNotice(msg);
            return { ok: false, message: msg };
          }
          const okMsg = (payload.data && typeof payload.data === "object" && "message" in payload.data ? String(payload.data.message) : null) ?? stringT(cfg, "saveSuccess", "Rules saved.");
          markSaved(json);
          setSaveNotice(okMsg);
          return { ok: true, message: okMsg };
        } catch (e) {
          const errMsg = String(e.message ?? e);
          const msg = stringT(cfg, "saveFailed", "Rules could not be saved: %s").replace("%s", errMsg);
          setSaveNotice(msg);
          return { ok: false, message: msg };
        } finally {
          setIsSaving(false);
        }
      },
      [cfg, markSaved]
    );
    return {
      savedSnapshot,
      setSavedSnapshot: markSaved,
      isSaving,
      saveNotice,
      setSaveNotice,
      hasUnsavedChanges,
      persistDocument
    };
  }
  function bootstrapFromTextarea(textareaId, cfg) {
    const ta = typeof document !== "undefined" ? document.getElementById(textareaId) : null;
    try {
      const parsed = parseDocumentFromTextarea(ta?.value ?? "", cfg);
      return { version: parsed.version, rules: parsed.rules, bootstrapError: null };
    } catch (e) {
      return {
        version: cfg.defaultVersion ?? "1.0.0",
        rules: [],
        bootstrapError: String(e.message ?? e)
      };
    }
  }
  function RulesVisualApp({ textareaId = "gro_rules_json" }) {
    const cfg = useMemo(() => normalizeConfig(window.groRulesEditor), []);
    const init = useMemo(() => bootstrapFromTextarea(textareaId, cfg), [textareaId, cfg]);
    const [version, setVersion] = useState(init.version);
    const [uiRules, setUiRules] = useState(init.rules);
    const [parseError, setParseError] = useState(init.bootstrapError);
    const [saveError, setSaveError] = useState(null);
    const { setSavedSnapshot, isSaving, saveNotice, hasUnsavedChanges, persistDocument } = useRulesPersistence(cfg);
    const versionRef = useRef(version);
    const uiRulesRef = useRef(uiRules);
    useEffect(() => {
      versionRef.current = version;
      uiRulesRef.current = uiRules;
    }, [version, uiRules]);
    const activeTabRef = useRef(null);
    const displayColumns = cfg.panelConfig?.displayColumns ?? 1;
    const didNormalizeTextareaRef = useRef(false);
    const didInitBaselineRef = useRef(false);
    const getTextarea = useCallback(() => document.getElementById(textareaId), [textareaId]);
    const flushToTextarea = useCallback(() => {
      const ta = getTextarea();
      if (!ta) return;
      ta.value = documentJson(versionRef.current, uiRulesRef.current, cfg);
    }, [cfg, getTextarea]);
    const importFromTextarea = useCallback(() => {
      const ta = getTextarea();
      if (!ta) throw new Error("Rules textarea missing");
      const parsed = parseDocumentFromTextarea(ta.value, cfg);
      setVersion(parsed.version);
      setUiRules(parsed.rules);
      setParseError(null);
    }, [cfg, getTextarea]);
    const syncPanelsForTab = useCallback((tab) => {
      const tabActiveClass = "gro-tabs__tab--active";
      document.querySelectorAll("[data-gro-tab]").forEach((el) => {
        const on = el.getAttribute("data-gro-tab") === tab;
        el.classList.toggle(tabActiveClass, on);
        el.classList.remove("is-primary", "is-secondary");
        if (on) {
          el.setAttribute("aria-current", "true");
        } else {
          el.removeAttribute("aria-current");
        }
        el.setAttribute("aria-selected", on ? "true" : "false");
      });
      document.querySelectorAll("[data-gro-panel]").forEach((panel) => {
        const on = panel.getAttribute("data-gro-panel") === tab;
        panel.hidden = !on;
        panel.toggleAttribute("hidden", !on);
        panel.setAttribute("aria-hidden", on ? "false" : "true");
      });
      const panelUrl = cfg.saveAjaxUrl;
      const panelAction = cfg.panelAction;
      const panelNonce = cfg.saveNonce;
      if (panelUrl && panelAction && panelNonce) {
        const body = new URLSearchParams();
        body.set("action", panelAction);
        body.set("nonce", panelNonce);
        body.set("tab", tab);
        void fetch(panelUrl, {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
          body: body.toString()
        });
      }
      const editorForm = document.getElementById("gro-rules-editor-form");
      if (editorForm) {
        const hideEditor = tab !== "visual";
        editorForm.hidden = hideEditor;
        editorForm.toggleAttribute("hidden", hideEditor);
      }
    }, []);
    const revealTab = useCallback(
      (next) => {
        activeTabRef.current = next;
        if (next !== "visual") {
          syncPanelsForTab(next);
          return;
        }
        syncPanelsForTab("visual");
        if (parseError === null) {
          try {
            importFromTextarea();
          } catch (e) {
            setParseError(String(e.message ?? e));
            setUiRules([]);
            setVersion(cfg.defaultVersion ?? "1.0.0");
          }
        }
      },
      [cfg.defaultVersion, importFromTextarea, parseError, syncPanelsForTab]
    );
    const revealTabRef = useRef(revealTab);
    revealTabRef.current = revealTab;
    const dirty = hasUnsavedChanges(version, uiRules);
    const handleSaveAll = useCallback(async () => {
      flushToTextarea();
      const result = await persistDocument(versionRef.current, uiRulesRef.current);
      if (!result.ok) {
        setSaveError(result.message);
      }
    }, [flushToTextarea, persistDocument]);
    useEffect(() => {
      document.dispatchEvent(
        new CustomEvent("gro-rules-state", {
          detail: { dirty: dirty && parseError === null, saving: isSaving }
        })
      );
    }, [dirty, isSaving, parseError]);
    useLayoutEffect(() => {
      const ta = getTextarea();
      const form = ta?.form ?? null;
      const tabList = document.querySelector(".gro-tabs");
      const onTabClick = (ev) => {
        const button = ev.target?.closest("[data-gro-tab]");
        const next = button?.getAttribute("data-gro-tab");
        if (next === "visual" || next === "patterns" || next === "email" || next === "runs" || next === "config" || next === "reset") {
          revealTabRef.current(next);
        }
      };
      tabList?.addEventListener("click", onTabClick);
      const onSubmit = (ev) => {
        if (activeTabRef.current !== "visual") return;
        ev.preventDefault();
        void handleSaveAll();
      };
      const onSidebarSave = () => {
        if (activeTabRef.current !== "visual") return;
        void handleSaveAll();
      };
      form?.addEventListener("submit", onSubmit);
      document.addEventListener("gro-sidebar-save", onSidebarSave);
      activeTabRef.current = null;
      const openTab = document.documentElement.dataset.groOpenRulesTab;
      delete document.documentElement.dataset.groOpenRulesTab;
      if (openTab === "config" || openTab === "reset" || openTab === "patterns" || openTab === "email" || openTab === "runs" || openTab === "visual") {
        revealTabRef.current(openTab);
      } else {
        revealTabRef.current("visual");
      }
      if (init.bootstrapError === null && !didNormalizeTextareaRef.current && ta) {
        didNormalizeTextareaRef.current = true;
        try {
          ta.value = documentJson(init.version, init.rules, cfg);
        } catch (e) {
          setParseError(String(e.message ?? e));
        }
      }
      if (!didInitBaselineRef.current && ta) {
        didInitBaselineRef.current = true;
        setSavedSnapshot(ta.value);
      }
      return () => {
        tabList?.removeEventListener("click", onTabClick);
        form?.removeEventListener("submit", onSubmit);
        document.removeEventListener("gro-sidebar-save", onSidebarSave);
      };
    }, []);
    useEffect(() => {
      if (activeTabRef.current === "visual" && parseError === null) {
        flushToTextarea();
      }
    }, [version, uiRules, flushToTextarea, parseError]);
    const addRule = () => {
      setUiRules((prev) => [
        ...prev,
        { type: "simple", editable: newEditableRule(cfg.actionTypes ?? ["log"], cfg.recurrences ?? ["daily"]) }
      ]);
    };
    const duplicateAt = useCallback(
      (ri) => {
        setUiRules((prev) => {
          const cur = prev[ri];
          if (!cur || cur.type !== "simple") return prev;
          const cloned = editableToRule(cur.editable);
          cloned.id = `${String(cloned.id)}_copy`;
          const nextEditable = ruleToEditable(cloned, cfg.recurrences ?? [], cfg.actionTypes ?? ["log"]);
          const out = prev.slice();
          out.splice(ri + 1, 0, { type: "simple", editable: nextEditable });
          return out;
        });
      },
      [cfg.actionTypes, cfg.recurrences]
    );
    const moveRule = useCallback((ri, delta) => {
      setUiRules((prev) => {
        const j = ri + delta;
        if (j < 0 || j >= prev.length) return prev;
        const n = prev.slice();
        const rowI = n[ri];
        const rowJ = n[j];
        if (!rowI || !rowJ) return prev;
        n[ri] = rowJ;
        n[j] = rowI;
        return n;
      });
    }, []);
    const Notice = window.wp.components.Notice;
    const enabledCount = parseError === null ? uiRules.filter((rule) => rule.type === "simple" ? rule.editable.enabled : rule.snapshot.enabled !== false).length : 0;
    return /* @__PURE__ */ element.createElement(CfgContext.Provider, { value: cfg }, /* @__PURE__ */ element.createElement("div", { className: "gro-rules-visual-inner" }, /* @__PURE__ */ element.createElement("header", { className: "gro-page-header" }, /* @__PURE__ */ element.createElement("div", { className: "gro-page-header__titles" }, /* @__PURE__ */ element.createElement("h1", { className: "gro-page-header__title" }, t(cfg, "automationsTitle", "Automation Rules")), /* @__PURE__ */ element.createElement("p", { className: "gro-page-header__lede" }, t(cfg, "automationsLede", "Build automations, then manage SMS patterns, email templates, and delivery settings separately."))), parseError === null ? /* @__PURE__ */ element.createElement("dl", { className: "gro-page-stats", "aria-label": t(cfg, "rulesSummary", "Rules summary") }, /* @__PURE__ */ element.createElement("div", { className: "gro-page-stats__item" }, /* @__PURE__ */ element.createElement("dt", null, t(cfg, "totalRules", "Total rules")), /* @__PURE__ */ element.createElement("dd", null, uiRules.length)), /* @__PURE__ */ element.createElement("div", { className: "gro-page-stats__item gro-page-stats__item--accent" }, /* @__PURE__ */ element.createElement("dt", null, t(cfg, "enabledRules", "Enabled")), /* @__PURE__ */ element.createElement("dd", null, enabledCount))) : null), /* @__PURE__ */ element.createElement("div", { className: "gro-rules-visual__toolbar" }, /* @__PURE__ */ element.createElement("div", { className: "gro-rules-visual__toolbar-primary" }, /* @__PURE__ */ element.createElement(GroButton, { variant: "primary", size: "lg", icon: "add", className: "gro-btn-add-rule", onClick: addRule, disabled: parseError !== null }, t(cfg, "addRule", "Add rule")))), parseError !== null ? /* @__PURE__ */ element.createElement(Notice, { status: "error", isDismissible: false }, parseError) : null, saveNotice !== null && parseError === null ? /* @__PURE__ */ element.createElement(Notice, { status: "success", isDismissible: false }, saveNotice) : null, parseError === null && uiRules.length === 0 ? /* @__PURE__ */ element.createElement("p", { className: "description gro-rules-visual__empty-hint" }, t(cfg, "noRules", "No rules yet. Add your first automation.")) : null, parseError === null && uiRules.length > 0 ? /* @__PURE__ */ element.createElement(
      "ul",
      {
        className: gridColsClass("gro-rules-rule-list", displayColumns),
        "aria-label": t(cfg, "ruleListHeading", "Configured rules")
      },
      uiRules.map((u, ri) => /* @__PURE__ */ element.createElement("li", { key: u.type === "advanced" ? `adv-${String(ri)}` : `simple-${String(ri)}`, className: "gro-rules-rule-list__item" }, u.type === "advanced" ? /* @__PURE__ */ element.createElement(
        AdvancedRuleCard,
        {
          ruleIndex: ri,
          snapshot: u.snapshot,
          onRemove: () => setUiRules((p) => p.filter((_, i) => i !== ri))
        }
      ) : /* @__PURE__ */ element.createElement(
        SimpleRuleCard,
        {
          ruleIndex: ri,
          editable: u.editable,
          onChange: (next) => setUiRules(
            (prev) => prev.map((row, idx) => idx === ri && row.type === "simple" ? { type: "simple", editable: next } : row)
          ),
          onDuplicate: () => duplicateAt(ri),
          onMove: (d) => moveRule(ri, d),
          onRemove: () => setUiRules((prev) => prev.filter((_, i) => i !== ri))
        }
      )))
    ) : null, /* @__PURE__ */ element.createElement(
      ConfirmDialog,
      {
        isOpen: saveError !== null,
        message: saveError ?? "",
        confirmLabel: t(cfg, "modalClose", "OK"),
        onConfirm: () => setSaveError(null),
        onCancel: () => setSaveError(null)
      }
    )));
  }
  const mount = document.getElementById("gro-rules-visual-root");
  if (mount) {
    createRoot(mount).render(
      /* @__PURE__ */ element.createElement(StrictMode, null, /* @__PURE__ */ element.createElement(RulesVisualApp, { textareaId: "gro_rules_json" }))
    );
  }
})();
//# sourceMappingURL=rules-visual-editor.bundle.js.map
