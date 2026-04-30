import { describe, expect, it } from 'vitest';
import { filterDocumentsByCascade } from './filterDocumentsByCascade';
const baseDoc = (overrides) => ({
    id: 'd1',
    template_id: 't1',
    template_version_id: null,
    title: 'Doc',
    study_type_id: null,
    study_id: 's1',
    module_id: 'm1',
    created_by: 'u1',
    owner_id: 'u1',
    status: 'draft',
    current_version: 1,
    submitted_at: null,
    published_at: null,
    ...overrides,
});
const hierarchy = [
    {
        id: 'st1',
        name: 'Tipo A',
        studies: [
            {
                id: 's1',
                study_type_id: 'st1',
                name: 'Estudio 1',
                course_modules: [{ id: 'm1', study_id: 's1', name: 'Mod 1' }],
            },
            {
                id: 's2',
                study_type_id: 'st1',
                name: 'Estudio 2',
                course_modules: [{ id: 'm2', study_id: 's2', name: 'Mod 2' }],
            },
        ],
    },
];
describe('filterDocumentsByCascade', () => {
    const docs = [
        baseDoc({ id: '1', study_id: 's1', module_id: 'm1' }),
        baseDoc({ id: '2', study_id: 's2', module_id: 'm2' }),
        baseDoc({ id: '3', study_id: null, module_id: null }),
    ];
    it('sin filtros activos devuelve la lista completa', () => {
        const empty = { studyTypeId: '', studyId: '', moduleId: '' };
        expect(filterDocumentsByCascade(docs, empty, hierarchy)).toEqual(docs);
    });
    it('filtra por módulo', () => {
        const r = filterDocumentsByCascade(docs, { studyTypeId: '', studyId: '', moduleId: 'm2' }, hierarchy);
        expect(r.map((d) => d.id)).toEqual(['2']);
    });
    it('filtra por estudio', () => {
        const r = filterDocumentsByCascade(docs, { studyTypeId: '', studyId: 's1', moduleId: '' }, hierarchy);
        expect(r.map((d) => d.id)).toEqual(['1']);
    });
    it('filtra por tipo de estudio (todos los estudios del tipo)', () => {
        const r = filterDocumentsByCascade(docs, { studyTypeId: 'st1', studyId: '', moduleId: '' }, hierarchy);
        expect(r.map((d) => d.id).sort()).toEqual(['1', '2']);
    });
    it('excluye documentos sin study_id al filtrar por tipo', () => {
        const r = filterDocumentsByCascade(docs, { studyTypeId: 'st1', studyId: '', moduleId: '' }, hierarchy);
        expect(r.some((d) => d.id === '3')).toBe(false);
    });
    it('devuelve vacío si el tipo no existe en jerarquía', () => {
        const r = filterDocumentsByCascade(docs, { studyTypeId: 'missing', studyId: '', moduleId: '' }, hierarchy);
        expect(r).toEqual([]);
    });
});
