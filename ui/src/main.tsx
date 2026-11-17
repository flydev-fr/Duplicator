import { createRoot } from 'react-dom/client';
import { App } from './App';
import { createApi, readBootstrap } from './api';
import './styles.css';

function mount() {
  const el = document.getElementById('duplicator-app');
  if (!el) return;
  const boot = readBootstrap();
  createRoot(el).render(<App boot={boot} api={createApi(boot)} />);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount);
else mount();
