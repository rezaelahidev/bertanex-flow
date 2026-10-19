import {
  computeDelaySeconds,
  defaultDelaySchedule,
  delayScheduleFromSeconds,
  mergeDelayIntoParams,
  parseDelayFromParams,
  stripDelayFromParams,
  type ActionDelaySchedule,
} from './delaySchedule';

export type { ActionDelaySchedule } from './delaySchedule';

export interface RulesPanelConfig {
  maxConditions?: number;
  maxActions?: number;
  displayColumns?: number;
}

export interface SmsTemplateChoice {
  id: string;
  label: string;
  body: string;
}

export interface GroRulesEditorConfig {
  saveAjaxUrl?: string;
  saveAction?: string;
  panelAction?: string;
  saveNonce?: string;
  panelConfig?: RulesPanelConfig;
  /** SMS templates from Configuration tab (id, label, resolved body). */
  smsTemplates?: SmsTemplateChoice[];
  defaultVersion?: string;
  recurrences?: string[];
  events?: { value: string; label: string }[];
  operators?: string[];
  /** Display labels for operator slugs (from PHP i18n). */
  operatorLabels?: Record<string, string>;
  actionTypes?: string[];
  actionTypeLabels?: Record<string, string>;
  recurrenceLabels?: Record<string, string>;
  /** Suggested condition field keys with human-readable labels (from PHP). */
  suggestedFields?: SuggestedConditionField[] | string[];
  strings?: Record<string, string>;
  /** When true, custom action dates use Jalali calendar UI (Persian/RTL admin). */
  useJalaliCalendar?: boolean;
  /** Twelve Jalali month labels (index 0 = Farvardin). */
  jalaliMonthNames?: string[];
}

export interface ConditionValueChoice {
  /** JSON-serializable value stored in rule `value`. */
  value: boolean | number | string;
  label: string;
}

export interface SuggestedConditionField {
  name: string;
  label: string;
  valueType?: 'boolean' | 'string' | 'integer' | 'number';
  values?: ConditionValueChoice[];
}

/** Operators where predefined single-value choices apply (arrays/regex use custom input). */
const PRESET_VALUE_OPS: Record<string, true> = {
  eq: true,
  neq: true,
  gt: true,
  gte: true,
  lt: true,
  lte: true,
  starts_with: true,
  ends_with: true,
  contains: true,
};

export type TriggerMode = 'none' | 'events' | 'schedule';

export interface EditableSchedule {
  hook: string;
  recurrence: string;
}

export interface EditableTrigger {
  mode: TriggerMode;
  events: string[];
  schedule: EditableSchedule;
}

export interface ConditionRow {
  field: string;
  op: string;
  valueJson: string;
  pattern: string;
}

export interface ActionRowUi {
  type: string;
  template_id: string;
  delay_seconds: number;
  delaySchedule: ActionDelaySchedule;
  paramsJson: string;
}

export interface EditableRule {
  id: string;
  label: string;
  enabled: boolean;
  priority: number;
  trigger: EditableTrigger;
  conditions: ConditionRow[];
  actions: ActionRowUi[];
}

export type UiRule =
  | { type: 'simple'; editable: EditableRule }
  | { type: 'advanced'; snapshot: Record<string, unknown> };

export interface RulesDocument {
  version?: string;
  rules: Record<string, unknown>[];
}

const OPERATORS_NO_VALUE: Record<string, true> = {
  empty: true,
  not_empty: true,
  truthy: true,
  falsy: true,
};

export function stringT(cfg: GroRulesEditorConfig, key: string, fallback: string): string {
  const s = cfg.strings?.[key];
  return s !== undefined ? String(s) : fallback;
}

/** Localized label for a slug, or the slug if no map entry. */
export function slugLabel(map: Record<string, string> | undefined, slug: string): string {
  const v = map?.[slug];
  return v !== undefined && v !== '' ? v : slug;
}

function normalizeValueChoice(raw: unknown): ConditionValueChoice | null {
  if (!raw || typeof raw !== 'object') return null;
  const row = raw as Record<string, unknown>;
  if (!Object.prototype.hasOwnProperty.call(row, 'value') || !Object.prototype.hasOwnProperty.call(row, 'label')) {
    return null;
  }
  const v = row.value;
  if (typeof v !== 'boolean' && typeof v !== 'number' && typeof v !== 'string') return null;
  const label = String(row.label ?? '').trim();
  if (label === '') return null;
  return { value: v, label };
}

/** Normalize PHP/filter payloads: string slugs or `{ name, label, values? }` objects. */
export function normalizeSuggestedConditionFields(
  raw: GroRulesEditorConfig['suggestedFields'],
): SuggestedConditionField[] {
  if (!raw?.length) return [];
  return raw
    .map((item) => {
      if (typeof item === 'string') {
        return { name: item, label: item };
      }
      const name = String(item.name ?? '').trim();
      const label = String(item.label ?? name).trim();
      const valueType =
        item.valueType === 'boolean' ||
        item.valueType === 'string' ||
        item.valueType === 'integer' ||
        item.valueType === 'number'
          ? item.valueType
          : undefined;
      const values = Array.isArray(item.values)
        ? item.values.map(normalizeValueChoice).filter((c): c is ConditionValueChoice => c !== null)
        : undefined;
      return {
        name,
        label: label || name,
        valueType,
        values: values?.length ? values : undefined,
      };
    })
    .filter((item) => item.name !== '');
}

export function findConditionField(
  fields: readonly SuggestedConditionField[],
  name: string,
): SuggestedConditionField | undefined {
  const key = name.trim();
  if (key === '') return undefined;
  return fields.find((f) => f.name === key);
}

export function valueJsonForChoice(choice: ConditionValueChoice): string {
  return JSON.stringify(choice.value);
}

export function parseValueJson(valueJson: string): unknown {
  const raw = String(valueJson ?? '').trim();
  if (raw === '') return undefined;
  try {
    return JSON.parse(raw) as unknown;
  } catch {
    return undefined;
  }
}

export function valueJsonMatchesChoice(valueJson: string, choice: ConditionValueChoice): boolean {
  const parsed = parseValueJson(valueJson);
  if (parsed === undefined) return false;
  try {
    return JSON.stringify(parsed) === JSON.stringify(choice.value);
  } catch {
    return false;
  }
}

export function findMatchingValueChoice(
  field: SuggestedConditionField | undefined,
  valueJson: string,
): ConditionValueChoice | undefined {
  if (!field?.values?.length) return undefined;
  return field.values.find((c) => valueJsonMatchesChoice(valueJson, c));
}

export function defaultValueJsonForField(field: SuggestedConditionField | undefined): string {
  const first = field?.values?.[0];
  return first ? valueJsonForChoice(first) : '';
}

export function operatorSupportsPresetValues(op: string): boolean {
  return Boolean(PRESET_VALUE_OPS[op]);
}

export function fieldHasPresetValues(
  field: SuggestedConditionField | undefined,
  op: string,
): boolean {
  return Boolean(field?.values?.length && operatorSupportsPresetValues(op));
}

/** Human-readable label for a stored condition value when choices are defined. */
export function conditionValueLabel(
  fields: readonly SuggestedConditionField[],
  fieldName: string,
  valueJson: string,
): string {
  const field = findConditionField(fields, fieldName);
  const match = findMatchingValueChoice(field, valueJson);
  if (match) return match.label;
  const raw = String(valueJson ?? '').trim();
  return raw === '' ? '' : raw;
}

/** Display label for a stored condition field name. */
export function conditionFieldLabel(fields: readonly SuggestedConditionField[], name: string): string {
  const key = name.trim();
  if (key === '') return '';
  const found = fields.find((f) => f.name === key);
  return found?.label ?? key;
}

export function parseDocumentJson(text: string, errMsg: string): RulesDocument {
  const data = JSON.parse(text) as unknown;
  if (!data || typeof data !== 'object' || !Array.isArray((data as RulesDocument).rules)) {
    throw new Error(errMsg);
  }
  return data as RulesDocument;
}

export function readTextareaDoc(textareaValue: string, defaultVersion: string): RulesDocument {
  const trimmed = textareaValue.trim();
  if (trimmed === '') {
    return { version: defaultVersion, rules: [] };
  }
  return parseDocumentJson(trimmed, '');
}

export function textareaParsesCleanly(textareaValue: string, defaultVersion: string): boolean {
  try {
    readTextareaDoc(textareaValue, defaultVersion);
    return true;
  } catch {
    return false;
  }
}

export function isSimpleLeaf(leaf: unknown): boolean {
  if (!leaf || typeof leaf !== 'object') return false;
  const o = leaf as Record<string, unknown>;
  if (typeof o.field !== 'string' || typeof o.op !== 'string') return false;
  for (const k of Object.keys(o)) {
    if (!['field', 'op', 'value', 'pattern'].includes(k)) return false;
  }
  return true;
}

export function isSimpleConditionGroup(c: unknown): boolean {
  if (!c || typeof c !== 'object') return false;
  const o = c as Record<string, unknown>;
  if ('any' in o || 'not' in o) return false;
  if (!Array.isArray(o.all)) return false;
  return o.all.every(isSimpleLeaf);
}

export function isSimpleTrigger(trigger: unknown, recurrences: readonly string[]): boolean {
  if (trigger === null || trigger === undefined) return true;
  if (typeof trigger !== 'object' || Array.isArray(trigger)) return false;
  const t = trigger as Record<string, unknown>;
  const keys = Object.keys(t);
  if (keys.length === 0) return true;
  const hasEv = Array.isArray(t.events);
  const hasSch = t.schedule !== undefined && t.schedule !== null && typeof t.schedule === 'object';
  if (hasEv && hasSch) return false;
  if (hasSch) {
    const s = t.schedule as Record<string, unknown>;
    return typeof s.hook === 'string' && recurrences.includes(String(s.recurrence));
  }
  if (hasEv) {
    return (t.events as unknown[]).every((e) => typeof e === 'string');
  }
  return false;
}

export function ruleIsVisuallyEditable(rule: Record<string, unknown>, recurrences: readonly string[]): boolean {
  return isSimpleConditionGroup(rule.conditions) && isSimpleTrigger(rule.trigger, recurrences);
}

export function defaultSchedule(recurrenceFallback: string): EditableSchedule {
  return { hook: '', recurrence: recurrenceFallback };
}

export function leafToRow(leaf: Record<string, unknown>): ConditionRow {
  const op = typeof leaf.op === 'string' ? leaf.op : 'eq';
  const row: ConditionRow = {
    field: typeof leaf.field === 'string' ? leaf.field : '',
    op,
    valueJson: '',
    pattern: typeof leaf.pattern === 'string' ? leaf.pattern : '',
  };
  if (op === 'regex') return row;
  if (OPERATORS_NO_VALUE[op]) return row;
  if (Object.prototype.hasOwnProperty.call(leaf, 'value')) {
    try {
      row.valueJson = JSON.stringify(leaf.value);
    } catch {
      row.valueJson = String(leaf.value);
    }
  }
  return row;
}

export function rowToLeaf(row: ConditionRow): Record<string, unknown> {
  const field = String(row.field || '').trim();
  const op = row.op || 'eq';
  const leaf: Record<string, unknown> = { field, op };
  if (OPERATORS_NO_VALUE[op]) return leaf;
  if (op === 'regex') {
    leaf.pattern = String(row.pattern || '');
    return leaf;
  }
  const raw = String(row.valueJson || '').trim();
  if (!raw) return leaf;
  leaf.value = JSON.parse(raw);
  return leaf;
}

export function triggerToEditable(
  trigger: unknown,
  recurrences: readonly string[],
  defaultRec: string,
): EditableTrigger {
  if (trigger && typeof trigger === 'object' && trigger !== null) {
    const t = trigger as Record<string, unknown>;
    const sch = t.schedule;
    if (sch && typeof sch === 'object' && sch !== null) {
      const s = sch as Record<string, unknown>;
      if (typeof s.hook === 'string' && s.hook !== '') {
        const rec = recurrences.includes(String(s.recurrence)) ? String(s.recurrence) : defaultRec;
        return { mode: 'schedule', events: [], schedule: { hook: s.hook, recurrence: rec } };
      }
    }
    if (Array.isArray(t.events)) {
      return {
        mode: 'events',
        events: t.events.filter((e): e is string => typeof e === 'string'),
        schedule: defaultSchedule(defaultRec),
      };
    }
  }
  return { mode: 'none', events: [], schedule: defaultSchedule(defaultRec) };
}

export function triggerFromEditable(te: EditableTrigger): Record<string, unknown> | undefined {
  if (te.mode === 'schedule') {
    const h = String(te.schedule.hook || '').trim();
    if (h === '') return undefined;
    return { schedule: { hook: h, recurrence: te.schedule.recurrence } };
  }
  if (te.mode === 'events' && te.events.length > 0) {
    const uniq: string[] = [];
    te.events.forEach((ev) => {
      if (typeof ev === 'string' && ev !== '' && !uniq.includes(ev)) uniq.push(ev);
    });
    if (uniq.length === 0) return undefined;
    return { events: uniq };
  }
  return undefined;
}

export function actionToRow(a: Record<string, unknown>, fallbackType: string): ActionRowUi {
  const type = typeof a.type === 'string' ? a.type : fallbackType;
  const templateId = typeof a.template_id === 'string' ? a.template_id : '';
  const delay = Object.prototype.hasOwnProperty.call(a, 'delay_seconds') ? Number(a.delay_seconds) : 0;
  const params =
    Object.prototype.hasOwnProperty.call(a, 'params') && a.params && typeof a.params === 'object'
      ? (a.params as Record<string, unknown>)
      : {};
  let paramsJson: string;
  try {
    paramsJson = JSON.stringify(params);
  } catch {
    paramsJson = '{}';
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
    paramsJson: JSON.stringify(stripDelayFromParams(params)),
  };
}

export function rowToAction(row: ActionRowUi): Record<string, unknown> {
  const out: Record<string, unknown> = { type: row.type };
  const tid = String(row.template_id || '').trim();
  if (tid !== '') out.template_id = tid;
  const delay = computeDelaySeconds(row.delaySchedule);
  if (delay > 0) out.delay_seconds = delay;
  const raw = String(row.paramsJson || '').trim();
  let params: Record<string, unknown> = {};
  if (raw !== '') {
    const parsed = JSON.parse(raw) as unknown;
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
      params = parsed as Record<string, unknown>;
    }
  }
  out.params = mergeDelayIntoParams(params, row.delaySchedule);
  return out;
}

export function ruleToEditable(
  rule: Record<string, unknown>,
  recurrences: readonly string[],
  actionTypes: readonly string[],
): EditableRule {
  const defaultRec = recurrences[0] ?? 'daily';
  const te = triggerToEditable(rule.trigger, recurrences, defaultRec);
  const condSrc =
    rule.conditions && typeof rule.conditions === 'object'
      ? (rule.conditions as { all?: unknown[] }).all
      : undefined;
  let conds: ConditionRow[] = Array.isArray(condSrc)
    ? condSrc.map((l) => leafToRow(l as Record<string, unknown>))
    : [{ field: '', op: 'eq', valueJson: 'true', pattern: '' }];
  if (conds.length === 0) conds = [{ field: '', op: 'eq', valueJson: '', pattern: '' }];
  const actionSrc = rule.actions;
  const at0 = actionTypes[0] ?? 'log';
  const actions =
    Array.isArray(actionSrc) && actionSrc.length > 0
      ? actionSrc.map((a) => actionToRow(a as Record<string, unknown>, at0))
      : [{ type: at0, template_id: '', delay_seconds: 0, delaySchedule: defaultDelaySchedule(), paramsJson: '{}' }];
  return {
    id: typeof rule.id === 'string' ? rule.id : `rule_${Date.now()}`,
    label: typeof rule.label === 'string' ? rule.label : '',
    enabled: typeof rule.enabled === 'boolean' ? rule.enabled : true,
    priority:
      typeof rule.priority === 'number' ? rule.priority : parseInt(String(rule.priority ?? 10), 10) || 10,
    trigger: te,
    conditions: conds,
    actions,
  };
}

export function editableToRule(edit: EditableRule): Record<string, unknown> {
  const conditionsAll = edit.conditions.map((c) => rowToLeaf(c));
  const actionsArr = edit.actions.map((a) => rowToAction(a));
  const tr = triggerFromEditable(edit.trigger);
  const out: Record<string, unknown> = {
    id: edit.id.trim() || `rule_${Date.now()}`,
    enabled: edit.enabled,
    priority: typeof edit.priority === 'number' && !Number.isNaN(edit.priority) ? edit.priority : 10,
    conditions: { all: conditionsAll },
    actions: actionsArr,
  };
  const lbl = edit.label.trim();
  if (lbl !== '') out.label = lbl;
  if (tr !== undefined) out.trigger = tr;
  return out;
}

export function importRules(rawRules: unknown[], recurrences: readonly string[], actionTypes: readonly string[]): UiRule[] {
  const ui: UiRule[] = [];
  for (const rule of rawRules) {
    if (!rule || typeof rule !== 'object') continue;
    const r = rule as Record<string, unknown>;
    if (!ruleIsVisuallyEditable(r, recurrences)) {
      ui.push({ type: 'advanced', snapshot: JSON.parse(JSON.stringify(r)) as Record<string, unknown> });
      continue;
    }
    ui.push({ type: 'simple', editable: ruleToEditable(r, recurrences, actionTypes) });
  }
  return ui;
}

export function serializeDocument(version: string, uiRules: UiRule[], fallbackVersion: string): RulesDocument {
  const rules = uiRules.map((u) =>
    u.type === 'advanced'
      ? (JSON.parse(JSON.stringify(u.snapshot)) as Record<string, unknown>)
      : editableToRule(u.editable),
  );
  return { version: version || fallbackVersion, rules };
}

export function newEditableRule(actionTypes: readonly string[], recurrences: readonly string[]): EditableRule {
  const defaultRec = recurrences[0] ?? 'daily';
  const at0 = actionTypes[0] ?? 'log';
  return ruleToEditable(
    {
      id: `new_rule_${Date.now()}`,
      label: '',
      enabled: true,
      priority: 10,
      trigger: null,
      conditions: { all: [{ field: '', op: 'eq', valueJson: 'true', pattern: '' }] },
      actions: [{ type: at0, template_id: '', delay_seconds: 0, params: {} }],
    },
    recurrences,
    actionTypes,
  );
}

export function opsNeedValue(op: string): boolean {
  return !OPERATORS_NO_VALUE[op] && op !== 'regex';
}

export function operatorOptions(cfg: GroRulesEditorConfig, current: string): string[] {
  const ops = [...(cfg.operators ?? ['eq'])];
  if (current && !ops.includes(current)) ops.unshift(current);
  return ops;
}

export function parseDocumentFromTextarea(text: string, cfg: GroRulesEditorConfig): { version: string; rules: UiRule[] } {
  const defaultVersion = cfg.defaultVersion ?? '1.0.0';
  const rec = cfg.recurrences ?? ['hourly', 'twicedaily', 'daily', 'weekly'];
  const actionTypes = cfg.actionTypes ?? ['send_sms'];
  const errRoot = stringT(cfg, 'errInvalidRoot', 'JSON must be an object with a "rules" array.');
  const trimmed = text.trim();
  if (trimmed === '') {
    return { version: defaultVersion, rules: [] };
  }
  const doc = parseDocumentJson(trimmed, errRoot);
  const version = typeof doc.version === 'string' && doc.version !== '' ? doc.version : defaultVersion;
  const rules = importRules(doc.rules ?? [], rec, actionTypes);
  return { version, rules };
}
