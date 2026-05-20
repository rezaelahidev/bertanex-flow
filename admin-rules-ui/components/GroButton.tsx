import { GroIcon, type GroIconName } from './GroIcon';
import React from 'react';

export type GroButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost';
export type GroButtonSize = 'sm' | 'md' | 'lg';

export interface GroButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  icon?: GroIconName;
  variant?: GroButtonVariant;
  size?: GroButtonSize;
  iconOnly?: boolean;
  children?: React.ReactNode;
}

export const GroButton = React.forwardRef<HTMLButtonElement, GroButtonProps>(function GroButton(
  {
  icon,
  variant = 'secondary',
  size = 'md',
  iconOnly = false,
  children,
  className = '',
  type = 'button',
  ...rest
},
  ref,
): React.ReactElement {
  const wpBase = variant === 'primary' ? 'button button-primary' : variant === 'danger' ? 'button button-link-delete' : 'button';
  const classes = [
    wpBase,
    'gro-btn',
    `gro-btn--${variant}`,
    `gro-btn--${size}`,
    iconOnly ? 'gro-btn--icon-only' : '',
    className,
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <button ref={ref} type={type} className={classes} {...rest}>
      {icon ? <GroIcon name={icon} /> : null}
      {children !== undefined && children !== null && children !== '' ? (
        <span className="gro-btn__label">{children}</span>
      ) : null}
    </button>
  );
});

GroButton.displayName = 'GroButton';
