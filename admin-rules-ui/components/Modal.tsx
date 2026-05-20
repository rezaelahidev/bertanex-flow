import { GroButton } from './GroButton';
import { t, useCfg } from './ConfigContext';
import React, { useEffect, useId, useRef } from 'react';

export interface ModalProps {
  title: string;
  isOpen: boolean;
  onClose: () => void;
  footer?: React.ReactNode;
  children: React.ReactNode;
}

export function Modal({ title, isOpen, onClose, footer, children }: ModalProps): React.ReactElement | null {
  const cfg = useCfg();
  const titleId = useId();
  const closeRef = useRef<HTMLButtonElement>(null);
  const onCloseRef = useRef(onClose);
  const wasOpenRef = useRef(false);

  onCloseRef.current = onClose;

  useEffect(() => {
    if (!isOpen) {
      wasOpenRef.current = false;
      return;
    }

    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    if (!wasOpenRef.current) {
      closeRef.current?.focus();
      wasOpenRef.current = true;
    }

    const onKey = (ev: KeyboardEvent): void => {
      if (ev.key === 'Escape') onCloseRef.current();
    };
    document.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = prev;
      document.removeEventListener('keydown', onKey);
    };
  }, [isOpen]);

  if (!isOpen) return null;

  return (
    <div className="gro-modal" role="presentation">
      <button type="button" className="gro-modal__backdrop" aria-label={t(cfg, 'modalClose', 'Close')} onClick={onClose} />
      <div className="gro-modal__dialog" role="dialog" aria-modal="true" aria-labelledby={titleId}>
        <header className="gro-modal__header">
          <h3 id={titleId} className="gro-modal__title">
            {title}
          </h3>
          <GroButton
            ref={closeRef}
            variant="ghost"
            size="sm"
            icon="close"
            iconOnly
            className="gro-modal__close"
            aria-label={t(cfg, 'modalClose', 'Close')}
            onClick={onClose}
          />
        </header>
        <div className="gro-modal__body">{children}</div>
        {footer !== undefined ? <footer className="gro-modal__footer">{footer}</footer> : null}
      </div>
    </div>
  );
}
