import React from 'react';

/** WordPress dashicons (available on admin screens). */
export type GroIconName =
  | 'add'
  | 'delete'
  | 'edit'
  | 'duplicate'
  | 'up'
  | 'down'
  | 'save'
  | 'close'
  | 'condition'
  | 'action'
  | 'trigger'
  | 'clock'
  | 'settings';

const DASHICON_CLASS: Record<GroIconName, string> = {
  add: 'dashicons-plus-alt2',
  delete: 'dashicons-trash',
  edit: 'dashicons-visibility',
  duplicate: 'dashicons-admin-page',
  up: 'dashicons-arrow-up-alt2',
  down: 'dashicons-arrow-down-alt2',
  save: 'dashicons-saved',
  close: 'dashicons-no-alt',
  condition: 'dashicons-filter',
  action: 'dashicons-controls-play',
  trigger: 'dashicons-calendar-alt',
  clock: 'dashicons-clock',
  settings: 'dashicons-admin-generic',
};

export interface GroIconProps {
  name: GroIconName;
  className?: string;
}

export function GroIcon({ name, className = '' }: GroIconProps): React.ReactElement {
  return <span className={`gro-icon dashicons ${DASHICON_CLASS[name]} ${className}`.trim()} aria-hidden="true" />;
}
