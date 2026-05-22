import {
  computeDelaySeconds,
  defaultDelaySchedule,
  formatDelayPreview,
  todayIsoDate,
  type ActionDelaySchedule,
  type ActionDelayTimeMode,
} from '../delaySchedule';
import { DateInput } from './DateInput';
import { GroIcon } from './GroIcon';
import { t, useCfg } from './ConfigContext';
import React, { useMemo } from 'react';

export interface ActionDelayFieldProps {
  schedule: ActionDelaySchedule;
  onChange: (next: ActionDelaySchedule, delaySeconds: number) => void;
}

function syncSchedule(schedule: ActionDelaySchedule, onChange: ActionDelayFieldProps['onChange']): void {
  onChange(schedule, computeDelaySeconds(schedule));
}

export function ActionDelayField({ schedule, onChange }: ActionDelayFieldProps): React.ReactElement {
  const cfg = useCfg();
  const minDate = todayIsoDate();
  const preview = useMemo(() => formatDelayPreview(schedule, cfg), [schedule, cfg]);

  const setActive = (active: boolean): void => {
    if (!active) {
      syncSchedule({ ...defaultDelaySchedule(), active: false }, onChange);
      return;
    }
    syncSchedule({ ...schedule, active: true }, onChange);
  };

  const patch = (partial: Partial<ActionDelaySchedule>): void => {
    syncSchedule({ ...schedule, ...partial }, onChange);
  };

  return (
    <div className="gro-action-delay">
      <div className="gro-action-delay__mode" role="group" aria-label={t(cfg, 'delayLabel', 'Delay')}>
        <button
          type="button"
          className={`gro-action-delay__mode-btn${!schedule.active ? ' is-active' : ''}`}
          aria-pressed={!schedule.active}
          onClick={() => setActive(false)}
        >
          <span className="gro-action-delay__mode-icon" aria-hidden>
            <GroIcon name="action" />
          </span>
          <span className="gro-action-delay__mode-text">
            <span className="gro-action-delay__mode-title">{t(cfg, 'delayImmediateShort', 'Immediate')}</span>
            <span className="gro-action-delay__mode-desc">
              {t(cfg, 'delayModeImmediateHint', 'When the rule matches')}
            </span>
          </span>
        </button>
        <button
          type="button"
          className={`gro-action-delay__mode-btn${schedule.active ? ' is-active' : ''}`}
          aria-pressed={schedule.active}
          onClick={() => setActive(true)}
        >
          <span className="gro-action-delay__mode-icon" aria-hidden>
            <GroIcon name="trigger" />
          </span>
          <span className="gro-action-delay__mode-text">
            <span className="gro-action-delay__mode-title">{t(cfg, 'delayModeSchedule', 'Schedule')}</span>
            <span className="gro-action-delay__mode-desc">
              {t(cfg, 'delayModeScheduleHint', 'Pick date and time after now')}
            </span>
          </span>
        </button>
      </div>

      {schedule.active ? (
        <>
          <div className="gro-action-delay__panels">
            <section className="gro-action-delay__panel" aria-labelledby="gro-delay-date-heading">
              <header className="gro-action-delay__panel-head" id="gro-delay-date-heading">
                <GroIcon name="trigger" className="gro-action-delay__panel-icon" />
                <span>{t(cfg, 'delayDateLegend', 'Date')}</span>
              </header>
              <div className="gro-action-delay__chips" role="group" aria-label={t(cfg, 'delayDateLegend', 'Date')}>
                <DelayChip
                  selected={schedule.dateMode === 'today'}
                  onClick={() => patch({ dateMode: 'today' })}
                >
                  {t(cfg, 'delayDateToday', 'Today')}
                </DelayChip>
                <DelayChip
                  selected={schedule.dateMode === 'days'}
                  onClick={() => patch({ dateMode: 'days', days: schedule.days > 0 ? schedule.days : 1 })}
                >
                  {t(cfg, 'delayDateInDays', 'In')} … {t(cfg, 'delayDateDaysLabel', 'days')}
                </DelayChip>
                <DelayChip
                  selected={schedule.dateMode === 'custom'}
                  onClick={() =>
                    patch({
                      dateMode: 'custom',
                      customDate: schedule.customDate || minDate,
                    })
                  }
                >
                  {t(cfg, 'delayDateOn', 'On')} …
                </DelayChip>
              </div>
              {schedule.dateMode === 'days' ? (
                <div className="gro-action-delay__detail">
                  <label className="gro-action-delay__detail-label">
                    <span>{t(cfg, 'delayDateInDays', 'In')}</span>
                    <input
                      type="number"
                      className="gro-action-delay__stepper"
                      min={1}
                      max={365}
                      value={schedule.days}
                      onChange={(ev) => patch({ dateMode: 'days', days: parseInt(ev.target.value, 10) || 1 })}
                    />
                    <span>{t(cfg, 'delayDateDaysLabel', 'days')}</span>
                  </label>
                </div>
              ) : null}
              {schedule.dateMode === 'custom' ? (
                <div className="gro-action-delay__detail">
                  <label className="gro-action-delay__detail-label">
                    <span>{t(cfg, 'delayDateOn', 'On')}</span>
                    <DateInput
                      className="gro-action-delay__date"
                      min={minDate}
                      value={schedule.customDate || minDate}
                      onChange={(iso) => patch({ dateMode: 'custom', customDate: iso })}
                    />
                  </label>
                </div>
              ) : null}
            </section>

            <section className="gro-action-delay__panel" aria-labelledby="gro-delay-time-heading">
              <header className="gro-action-delay__panel-head" id="gro-delay-time-heading">
                <GroIcon name="clock" className="gro-action-delay__panel-icon" />
                <span>{t(cfg, 'delayTimeLegend', 'Time')}</span>
              </header>
              <div className="gro-action-delay__chips" role="group" aria-label={t(cfg, 'delayTimeLegend', 'Time')}>
                <DelayChip
                  selected={schedule.timeMode === 'none'}
                  onClick={() => patch({ timeMode: 'none', relativeAmount: 0 })}
                >
                  {t(cfg, 'delayTimeNoneShort', 'No extra time')}
                </DelayChip>
                <DelayChip
                  selected={schedule.timeMode === 'seconds'}
                  onClick={() => patch({ timeMode: 'seconds', relativeAmount: schedule.relativeAmount > 0 ? schedule.relativeAmount : 30 })}
                >
                  + {t(cfg, 'delayTimeSeconds', 'seconds')}
                </DelayChip>
                <DelayChip
                  selected={schedule.timeMode === 'minutes'}
                  onClick={() => patch({ timeMode: 'minutes', relativeAmount: schedule.relativeAmount > 0 ? schedule.relativeAmount : 5 })}
                >
                  + {t(cfg, 'delayTimeMinutes', 'minutes')}
                </DelayChip>
                <DelayChip
                  selected={schedule.timeMode === 'hours'}
                  onClick={() => patch({ timeMode: 'hours', relativeAmount: schedule.relativeAmount > 0 ? schedule.relativeAmount : 1 })}
                >
                  + {t(cfg, 'delayTimeHours', 'hours')}
                </DelayChip>
                <DelayChip
                  selected={schedule.timeMode === 'custom'}
                  onClick={() =>
                    patch({
                      timeMode: 'custom',
                      customTime: schedule.customTime || defaultCustomTime(),
                    })
                  }
                >
                  {t(cfg, 'delayTimeAt', 'At')} …
                </DelayChip>
              </div>
              {schedule.timeMode !== 'none' && schedule.timeMode !== 'custom' ? (
                <div className="gro-action-delay__detail">
                  <RelativeTimeDetail
                    mode={schedule.timeMode}
                    amount={schedule.relativeAmount}
                    cfg={cfg}
                    onAmount={(amount) => patch({ timeMode: schedule.timeMode, relativeAmount: amount })}
                  />
                </div>
              ) : null}
              {schedule.timeMode === 'custom' ? (
                <div className="gro-action-delay__detail">
                  <label className="gro-action-delay__detail-label">
                    <span>{t(cfg, 'delayTimeAt', 'At')}</span>
                    <input
                      type="time"
                      className="gro-action-delay__time"
                      value={schedule.customTime || defaultCustomTime()}
                      onChange={(ev) => patch({ timeMode: 'custom', customTime: ev.target.value })}
                    />
                  </label>
                </div>
              ) : null}
            </section>
          </div>

          <div className="gro-action-delay__preview" role="status">
            <span className="gro-action-delay__preview-icon" aria-hidden />
            <span className="gro-action-delay__preview-body">
              <span className="gro-action-delay__preview-label">{t(cfg, 'delayPreviewLabel', 'Preview:')}</span>
              <span className="gro-action-delay__preview-text">{preview}</span>
            </span>
          </div>
        </>
      ) : null}
    </div>
  );
}

function defaultCustomTime(): string {
  const d = new Date();
  d.setMinutes(d.getMinutes() + 30);
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

function DelayChip({
  selected,
  onClick,
  children,
}: {
  selected: boolean;
  onClick: () => void;
  children: React.ReactNode;
}): React.ReactElement {
  return (
    <button type="button" className={`gro-delay-chip${selected ? ' is-selected' : ''}`} aria-pressed={selected} onClick={onClick}>
      {children}
    </button>
  );
}

function RelativeTimeDetail({
  mode,
  amount,
  cfg,
  onAmount,
}: {
  mode: 'seconds' | 'minutes' | 'hours';
  amount: number;
  cfg: ReturnType<typeof useCfg>;
  onAmount: (n: number) => void;
}): React.ReactElement {
  const unit =
    mode === 'hours'
      ? t(cfg, 'delayTimeHours', 'hours')
      : mode === 'minutes'
        ? t(cfg, 'delayTimeMinutes', 'minutes')
        : t(cfg, 'delayTimeSeconds', 'seconds');
  const max = mode === 'hours' ? 168 : mode === 'minutes' ? 10080 : 86400;

  return (
    <label className="gro-action-delay__detail-label">
      <span>+</span>
      <input
        type="number"
        className="gro-action-delay__stepper"
        min={1}
        max={max}
        value={amount > 0 ? amount : 1}
        onChange={(ev) => onAmount(parseInt(ev.target.value, 10) || 1)}
      />
      <span>{unit}</span>
    </label>
  );
}
