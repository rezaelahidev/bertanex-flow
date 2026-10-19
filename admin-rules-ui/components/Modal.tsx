import React from 'react';

export interface ModalProps {
  title: string;
  isOpen: boolean;
  onClose: () => void;
  footer?: React.ReactNode;
  children: React.ReactNode;
}

export function Modal({ title, isOpen, onClose, footer, children }: ModalProps): React.ReactElement | null {
  if (!isOpen) return null;
  const WpModal = window.wp.components.Modal;

  return (
    <WpModal title={title} onRequestClose={onClose} size="large" className="gro-editor-modal">
      <div className="gro-editor-modal__body">{children}</div>
      {footer !== undefined ? <div className="gro-editor-modal__footer">{footer}</div> : null}
    </WpModal>
  );
}
