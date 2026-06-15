import { LoadingState } from '../../../components/ui/LoadingState';

export function ShiftLoadingState({ label = 'Loading scoped shift assignments' }) {
  return <LoadingState className="tr:min-h-56" label={label} />;
}
