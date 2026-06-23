import { zodResolver } from '@hookform/resolvers/zod';
import { useCallback, useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { type DocumentStep1Input, documentStep1Schema } from '../schemas/documentStep1';

/**
 * Step-1 (properties) form for the document wizard: wraps react-hook-form with
 * the zod resolver, exposes watched values + plain setters, and projects RHF's
 * `formState.errors` into a flat `errors` map with a `setErrors` bridge that the
 * cross-field (derived) validations write to.
 */
export function useDocumentStep1Form() {
  const step1Methods = useForm<DocumentStep1Input>({
    defaultValues: {
      title: '',
      deliveryDeadline: '',
      studyTypeId: '',
      studyId: '',
      moduleId: '',
      teamId: '',
    },
    resolver: zodResolver(documentStep1Schema),
    mode: 'onChange',
  });
  const {
    setValue: setStep1Value,
    watch: watchStep1,
    setError: setStep1Error,
    clearErrors: clearStep1Errors,
    handleSubmit: handleStep1Submit,
    formState: { errors: step1Errors },
  } = step1Methods;
  const title = watchStep1('title');
  const deliveryDeadline = watchStep1('deliveryDeadline');
  const studyTypeId = watchStep1('studyTypeId');
  const studyId = watchStep1('studyId');
  const moduleId = watchStep1('moduleId');
  const teamId = watchStep1('teamId');
  const setTitle = useCallback(
    (v: string) => setStep1Value('title', v, { shouldDirty: true, shouldValidate: false }),
    [setStep1Value],
  );
  const setDeliveryDeadline = useCallback(
    (v: string) =>
      setStep1Value('deliveryDeadline', v, { shouldDirty: true, shouldValidate: false }),
    [setStep1Value],
  );
  const setStudyTypeId = useCallback(
    (v: string) => setStep1Value('studyTypeId', v, { shouldDirty: true, shouldValidate: false }),
    [setStep1Value],
  );
  const setStudyId = useCallback(
    (v: string) => setStep1Value('studyId', v, { shouldDirty: true, shouldValidate: false }),
    [setStep1Value],
  );
  const setModuleId = useCallback(
    (v: string) => setStep1Value('moduleId', v, { shouldDirty: true, shouldValidate: false }),
    [setStep1Value],
  );
  const setTeamId = useCallback(
    (v: string) => setStep1Value('teamId', v, { shouldDirty: true, shouldValidate: false }),
    [setStep1Value],
  );
  // Cross-field errors that depend on derived flags (require*) live alongside RHF formState.errors.
  const errors: Record<string, string> = useMemo(() => {
    const map: Record<string, string> = {};
    if (step1Errors.title?.message) map.title = step1Errors.title.message;
    if (step1Errors.deliveryDeadline?.message)
      map.deliveryDeadline = step1Errors.deliveryDeadline.message;
    if (step1Errors.studyTypeId?.message) map.studyTypeId = step1Errors.studyTypeId.message;
    if (step1Errors.studyId?.message) map.studyId = step1Errors.studyId.message;
    if (step1Errors.moduleId?.message) map.moduleId = step1Errors.moduleId.message;
    if (step1Errors.teamId?.message) map.teamId = step1Errors.teamId.message;
    return map;
  }, [step1Errors]);
  const setErrors = useCallback(
    (
      updater: Record<string, string> | ((prev: Record<string, string>) => Record<string, string>),
    ) => {
      const next = typeof updater === 'function' ? updater(errors) : updater;
      const keys: (keyof DocumentStep1Input)[] = [
        'title',
        'deliveryDeadline',
        'studyTypeId',
        'studyId',
        'moduleId',
        'teamId',
      ];
      for (const k of keys) {
        if (next[k]) setStep1Error(k, { type: 'manual', message: next[k] });
        else clearStep1Errors(k);
      }
    },
    [errors, setStep1Error, clearStep1Errors],
  );

  return {
    title,
    setTitle,
    deliveryDeadline,
    setDeliveryDeadline,
    studyTypeId,
    setStudyTypeId,
    studyId,
    setStudyId,
    moduleId,
    setModuleId,
    teamId,
    setTeamId,
    errors,
    setErrors,
    handleStep1Submit,
    clearStep1Errors,
    setStep1Error,
  };
}

export type DocumentStep1Form = ReturnType<typeof useDocumentStep1Form>;
