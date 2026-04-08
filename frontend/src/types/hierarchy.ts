export interface CourseModule {
  id: string;
  study_id: string;
  name: string;
}

export interface Study {
  id: string;
  study_type_id: string;
  name: string;
  course_modules: CourseModule[];
}

export interface StudyType {
  id: string;
  name: string;
  studies: Study[];
}

export type AcademicHierarchy = StudyType[];
