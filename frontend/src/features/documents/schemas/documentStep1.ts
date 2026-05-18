import { z } from 'zod'

export const documentStep1Schema = z
  .object({
    title: z
      .string()
      .transform((s) => s.trim())
      .refine((s) => s.length > 0, { message: 'El título es obligatorio.' }),
    deliveryDeadline: z.string(),
    studyTypeId: z.string(),
    studyId: z.string(),
    moduleId: z.string(),
    teamId: z.string(),
  })
  .superRefine((data, ctx) => {
    if (!data.deliveryDeadline) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['deliveryDeadline'],
        message: 'La fecha de entrega es obligatoria.',
      })
      return
    }
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    if (new Date(data.deliveryDeadline) < today) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['deliveryDeadline'],
        message: 'La fecha no puede ser anterior a hoy.',
      })
    }
  })

export type DocumentStep1Input = {
  title: string
  deliveryDeadline: string
  studyTypeId: string
  studyId: string
  moduleId: string
  teamId: string
}

export const emptyDocumentStep1: DocumentStep1Input = {
  title: '',
  deliveryDeadline: '',
  studyTypeId: '',
  studyId: '',
  moduleId: '',
  teamId: '',
}
