import { z } from 'zod'
import type { TemplateVisibilityLevel } from '../../../types/templates'

const VISIBILITY_VALUES = ['personal', 'global', 'study_type', 'study', 'module', 'team'] as const

export const templateStep1Schema = z
  .object({
    name: z
      .string()
      .transform((s) => s.trim())
      .refine((s) => s.length > 0, { message: 'El nombre es obligatorio.' }),
    description: z.string(),
    visibility: z.enum(VISIBILITY_VALUES),
    deliveryDeadline: z.string(),
    studyTypeId: z.string(),
    studyId: z.string(),
    moduleId: z.string(),
    teamId: z.string(),
    themeId: z.string(),
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
    if (!data.deliveryDeadline) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['deliveryDeadline'],
        message: 'El plazo de entrega es obligatorio.',
      })
    } else {
      const today = new Date()
      today.setHours(0, 0, 0, 0)
      const selected = new Date(data.deliveryDeadline)
      if (selected < today) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['deliveryDeadline'],
          message: 'La fecha no puede ser anterior a hoy.',
        })
      }
    }
  })

export type TemplateStep1Input = {
  name: string
  description: string
  visibility: TemplateVisibilityLevel
  deliveryDeadline: string
  studyTypeId: string
  studyId: string
  moduleId: string
  teamId: string
  /** UUID del theme (vacío = sin theme). */
  themeId: string
}

export const emptyTemplateStep1: TemplateStep1Input = {
  name: '',
  description: '',
  visibility: 'personal',
  deliveryDeadline: '',
  studyTypeId: '',
  studyId: '',
  moduleId: '',
  teamId: '',
  themeId: '',
}
