import type { SettingValues } from './api';

export function formatSize(bytes: number): string {
  if (!bytes) return '—';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  const value = bytes / Math.pow(1024, i);
  return `${value >= 10 || i === 0 ? Math.round(value) : value.toFixed(1)} ${units[i]}`;
}

const relative = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

export function timeAgo(unix: number): string {
  if (!unix) return '—';
  const seconds = Math.round(unix - Date.now() / 1000);
  const steps: [number, Intl.RelativeTimeFormatUnit][] = [
    [60, 'second'],
    [60, 'minute'],
    [24, 'hour'],
    [7, 'day'],
    [4.35, 'week'],
    [12, 'month'],
    [Infinity, 'year'],
  ];
  let value = seconds;
  for (const [size, unit] of steps) {
    if (Math.abs(value) < size) return relative.format(Math.round(value), unit);
    value /= size;
  }
  return '';
}

export function fullDate(unix: number): string {
  return unix ? new Date(unix * 1000).toLocaleString() : '';
}

/**
 * ProcessWire showIf / requiredIf rules as used by the module: "a=1", "a!=1", "a='x'",
 * several conditions joined by "," (and), alternatives with "|".
 */
export function matchesCondition(rule: string, values: SettingValues): boolean {
  if (!rule.trim()) return true;
  return rule.split(',').every((part) => {
    const m = part.trim().match(/^([\w.]+)\s*(!=|=)\s*(.*)$/);
    if (!m) return true;
    const [, name, op, raw] = m;
    const wanted = raw.split('|').map((v) => v.trim().replace(/^['"]|['"]$/g, ''));
    const current = values[name];
    const currentValues = Array.isArray(current) ? current.map(String) : [String(current ?? '')];
    const hit = wanted.some((w) => currentValues.includes(w) || (w === '' && currentValues.every((c) => c === '' || c === '0')));
    return op === '=' ? hit : !hit;
  });
}

export function serviceLabel(service: string): string {
  return ({ local: 'Local folder', dropbox: 'Dropbox', googledrive: 'Google Drive', amazon: 'Amazon S3', ftp: 'FTP' } as Record<string, string>)[service] ?? service;
}
