import { z } from 'zod'

export const documentStep1Schema = z.object({
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
