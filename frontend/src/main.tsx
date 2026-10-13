import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import { QueryCache, QueryClient, QueryClientProvider } from '@tanstack/react-query';
import App from './App';
import { SessionProvider } from './lib/session';
import { ApiError, appBase } from './lib/api';

// a page the person may not open (403) or that is gone (404): tell them, instead of leaving a blank screen
const qc: QueryClient = new QueryClient({ queryCache: new QueryCache({ onError: (e) => {
  if (e instanceof ApiError && [403, 404].includes(e.status)) window.dispatchEvent(new CustomEvent('inv-denied', { detail: e.status }));
  // the sign-in ran out (another tab signed out, session expired): ask who we are again, which sends the page to the sign-in screen
  if (e instanceof ApiError && e.status === 401) { window.dispatchEvent(new CustomEvent('inv-expired')); qc.invalidateQueries({ queryKey: ['admin-session'] }); }
} }), defaultOptions: { queries: { retry: false, refetchOnWindowFocus: false } } });

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={qc}>
      <BrowserRouter basename={appBase}>
        <SessionProvider><App /></SessionProvider>
      </BrowserRouter>
    </QueryClientProvider>
  </StrictMode>,
);
