import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react';
import { Icon } from './icons';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';

export function Button({
  children,
  variant = 'secondary',
  icon,
  busy = false,
  ...rest
}: { variant?: Variant; icon?: string; busy?: boolean } & React.ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button type="button" className={`dup-btn dup-btn-${variant}`} disabled={busy || rest.disabled} aria-busy={busy || undefined} {...rest}>
      {busy ? <span className="dup-spinner" aria-hidden="true" /> : icon ? <Icon name={icon} /> : null}
      {children}
    </button>
  );
}

export function IconButton({ icon, label, href, onClick, tone }: { icon: string; label: string; href?: string; onClick?: () => void; tone?: 'danger' }) {
  const className = `dup-icon-btn${tone ? ' dup-icon-btn-' + tone : ''}`;
  if (href) {
    return (
      <a className={className} href={href} title={label} aria-label={label}>
        <Icon name={icon} />
      </a>
    );
  }
  return (
    <button type="button" className={className} onClick={onClick} title={label} aria-label={label}>
      <Icon name={icon} />
    </button>
  );
}

/** Confirmation with the native <dialog> element (focus trap and Escape for free) */
export function useConfirm() {
  const [request, setRequest] = useState<{ title: string; body: ReactNode; action: string; resolve: (ok: boolean) => void } | null>(null);
  const ref = useRef<HTMLDialogElement>(null);

  useEffect(() => {
    if (request && ref.current && !ref.current.open) ref.current.showModal();
  }, [request]);

  const confirm = useCallback(
    (title: string, body: ReactNode, action = 'Delete') => new Promise<boolean>((resolve) => setRequest({ title, body, action, resolve })),
    [],
  );

  const close = (ok: boolean) => {
    ref.current?.close();
    request?.resolve(ok);
    setRequest(null);
  };

  const dialog = request ? (
    <dialog ref={ref} className="dup-dialog" onCancel={(e) => { e.preventDefault(); close(false); }}>
      <h3>{request.title}</h3>
      <div className="dup-dialog-body">{request.body}</div>
      <div className="dup-dialog-actions">
        <Button onClick={() => close(false)}>Cancel</Button>
        <Button variant="danger" onClick={() => close(true)} autoFocus>
          {request.action}
        </Button>
      </div>
    </dialog>
  ) : null;

  return { confirm, dialog };
}

interface Toast {
  id: number;
  tone: 'ok' | 'error';
  text: string;
}

const ToastContext = createContext<(tone: Toast['tone'], text: string) => void>(() => {});

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const push = useCallback((tone: Toast['tone'], text: string) => {
    const id = Date.now() + Math.random();
    setToasts((t) => [...t, { id, tone, text }]);
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), tone === 'error' ? 8000 : 4000);
  }, []);
  return (
    <ToastContext.Provider value={push}>
      {children}
      <div className="dup-toasts" role="status" aria-live="polite">
        {toasts.map((t) => (
          <div key={t.id} className={`dup-toast dup-toast-${t.tone}`}>
            <Icon name={t.tone === 'ok' ? 'check' : 'alert'} />
            <span>{t.text}</span>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

export const useToast = () => useContext(ToastContext);
