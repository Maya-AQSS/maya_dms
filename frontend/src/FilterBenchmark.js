// Benchmark de filtrado síncrono (Requisito: < 16ms)
const iterations = 1000;
const itemsCount = 5000;

// 1. Generación de datos sintéticos
const hierarchy = [
    { id: 'T1', name: 'ESO', studies: [{ id: 'S1', name: '1º ESO', course_modules: [{ id: 'M1', name: 'Math' }, { id: 'M2', name: 'English' }] }] },
    { id: 'T2', name: 'FP', studies: [{ id: 'S2', name: 'DAW', course_modules: [{ id: 'M3', name: 'DWES' }, { id: 'M4', name: 'DWECL' }] }] }
];

const allDocuments = [];
for (let i = 0; i < itemsCount; i++) {
    const type = hierarchy[i % 2];
    const study = type.studies[0];
    const module = study.course_modules[i % 2];
    allDocuments.push({
        id: `doc-${i}`,
        study_id: study.id,
        course_module_id: module.id
    });
}

// 2. Lógica de filtrado (idéntica a la de Alpine.js)
function filterDocuments(docs, typeId, studyId, moduleId) {
    return docs.filter(doc => {
        const matchesType = !typeId || hierarchy.find(t => t.id === typeId)?.studies.some(s => s.id === doc.study_id);
        const matchesStudy = !studyId || doc.study_id === studyId;
        const matchesModule = !moduleId || doc.course_module_id === moduleId;
        return matchesType && matchesStudy && matchesModule;
    });
}

// 3. Medición
console.log(`Iniciando benchmark con ${itemsCount} items y ${iterations} iteraciones...`);
const start = performance.now();

for (let j = 0; j < iterations; j++) {
    // Escenario: Filtrado por módulo (el más específico)
    filterDocuments(allDocuments, 'T1', 'S1', 'M1');
}

const end = performance.now();
const avg = (end - start) / iterations;

console.log('--- RESULTADOS ---');
console.log(`Tiempo medio de filtrado: ${avg.toFixed(4)} ms`);
console.log(`Cumple requisito (< 16ms): ${avg < 16 ? 'SÍ ✅' : 'NO ❌'}`);

if (avg >= 16) {
    process.exit(1);
}
