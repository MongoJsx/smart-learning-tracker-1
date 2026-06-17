const SUBJECTS_UPDATED_EVENT = 'subjects:updated';

export const emitSubjectsUpdated = () => {
  if (typeof window === 'undefined') return;
  window.dispatchEvent(new Event(SUBJECTS_UPDATED_EVENT));
};

export const subscribeSubjectsUpdated = (listener: () => void) => {
  if (typeof window === 'undefined') return () => {};
  window.addEventListener(SUBJECTS_UPDATED_EVENT, listener);
  return () => window.removeEventListener(SUBJECTS_UPDATED_EVENT, listener);
};
