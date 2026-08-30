import { useState } from 'react';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import { EmptyState } from '../../components/ui/EmptyState';
import { LoadingState } from '../../components/ui/LoadingState';
import { formatDisplayDate } from '../../lib/date';

export function SandboxApp() {
  const [darkMode, setDarkMode] = useState(false);
  const [showLoading, setShowLoading] = useState(false);

  return (
    <main
      className="tracs-react-root foundation-sandbox tr:min-h-screen tr:bg-tracs-page tr:p-tracs-6 tr:text-tracs-primary"
      data-theme={darkMode ? 'dark' : 'light'}
    >
      <div className="tr:mx-auto tr:flex tr:max-w-4xl tr:flex-col tr:gap-tracs-5">
        <header className="tr:flex tr:flex-wrap tr:items-center tr:justify-between tr:gap-tracs-3">
          <div>
            <p className="tr:mb-tracs-1 tr:text-xs tr:font-semibold tr:uppercase tr:tracking-wide tr:text-tracs-accent">
              Isolated validation only
            </p>
            <h1 className="tr:text-xl tr:font-bold">TRACS frontend foundation</h1>
            <p className="tr:mt-tracs-1 tr:text-sm tr:text-tracs-secondary">
              Calendar remains the visual reference. No production page loads this sandbox.
            </p>
          </div>
          <Button onClick={() => setDarkMode((current) => !current)} variant="secondary">
            Use {darkMode ? 'light' : 'dark'} theme
          </Button>
        </header>

        <Card className="tr:flex tr:flex-col tr:gap-tracs-4">
          <div className="tr:flex tr:flex-wrap tr:items-center tr:justify-between tr:gap-tracs-3">
            <div>
              <h2 className="tr:text-base tr:font-bold">Shared primitive check</h2>
              <p className="tr:mt-tracs-1 tr:text-sm tr:text-tracs-secondary">
                UI date: {formatDisplayDate('2026-06-14')}
              </p>
            </div>
            <div className="tr:flex tr:flex-wrap tr:gap-tracs-2">
              <Badge variant="success">Build isolated</Badge>
              <Badge variant="info">No Preflight</Badge>
              <Badge>tr: prefix</Badge>
            </div>
          </div>

          <div className="tr:flex tr:flex-wrap tr:gap-tracs-2">
            <Button variant="primary">Primary action</Button>
            <Button onClick={() => setShowLoading((current) => !current)}>
              Toggle state
            </Button>
            <Button variant="danger">Danger action</Button>
            <Button variant="quiet">Quiet action</Button>
          </div>

          {showLoading ? (
            <LoadingState label="Validating the isolated state" />
          ) : (
            <EmptyState
              description="Future modules will provide real data through authenticated PHP APIs."
              title="No production data connected"
            />
          )}
        </Card>
      </div>
    </main>
  );
}
