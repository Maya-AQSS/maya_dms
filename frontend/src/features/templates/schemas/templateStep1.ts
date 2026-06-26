import { z } from 'zod'
import type { TemplateVisibilityLevel } from '../../../types/templates'

const VISIBILITY_VALUES = ['personal', 'global', 'study_type', 'study', 'module', 'team'] as const

function validateFutureDeadline(value: string, path: string, ctx: z.RefinementCtx, messageRequired: string) {
  if (!value) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: [path],
      message: messageRequired,
    })
    return
  }
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  if (new Date(value) < today) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: [path],
      message: 'La fecha no puede ser anterior a hoy.',
    })
  }
}

export const templateStep1Schema = z
  .object({
    name: z
      .string()
      .transform((s) => s.trim())
      .refine((s) => s.length > 0, { message: 'El nombre es obligatorio.' }),
    description: z.string(),
    visibility: z.enum(VISIBILITY_VALUES),
    deliveryDeadline: z.string(),
    documentDeliveryDeadline: z.string(),
    studyTypeId: z.string(),
    studyId: z.string(),
    moduleId: z.string(),
    teamId: z.string(),
    themeId: z.string(),
    createdBy: z.string().optional(),
  })
  .superRefine((data, ctx) => {
    if (data.visibility === 'study_type' || data.visibility === 'study' || data.visibility === 'module') {
      if (!data.studyTypeId) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['studyTypeId'], message: 'Este campo es obligatorio' })
      }
    }
    if (data.visibility === 'study' || data.visibility === 'module') {
      if (!data.studyId) {
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['studyId'], message: 'Este campo es obligatorio' })
      }
    }
    if (data.visibility === 'module' && !data.moduleId) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['moduleId'], message: 'Este campo es obligatorio' })
    }
    if (data.visibility === 'team' && !data.teamId) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['teamId'], message: 'Este campo es obligatorio' })
    }
    validateFutureDeadline(data.deliveryDeadline, 'deliveryDeadline', ctx, 'El plazo de validación de la plantilla es obligatorio.')
    validateFutureDeadline(
      data.documentDeliveryDeadline,
      'documentDeliveryDeadline',
      ctx,
      'La fecha límite de validación del documento es obligatoria.',
    )
    if (data.deliveryDeadline && data.documentDeliveryDeadline) {
      const templateDate = new Date(data.deliveryDeadline)
      const documentDate = new Date(data.documentDeliveryDeadline)
      templateDate.setHours(0, 0, 0, 0)
      documentDate.setHours(0, 0, 0, 0)
      if (documentDate < templateDate) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['documentDeliveryDeadline'],
          message: 'La fecha del documento no puede ser anterior a la de validación de la plantilla.',
        })
      }
    }
  })

export type TemplateStep1Input = {
  name: string
  description: string
  visibility: TemplateVisibilityLevel
  deliveryDeadline: string
  documentDeliveryDeadline: string
  studyTypeId: string
  studyId: string
  moduleId: string
  teamId: string
  /** UUID del theme (vacío = sin theme). */
  themeId: string
  createdBy?: string
}

export const emptyTemplateStep1: TemplateStep1Input = {
  name: '',
  description: '',
  visibility: 'personal',
  deliveryDeadline: '',
  documentDeliveryDeadline: '',
  studyTypeId: '',
  studyId: '',
  moduleId: '',
  teamId: '',
  themeId: '',
}
