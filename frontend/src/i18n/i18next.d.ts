import'react-i18next';
import type { resources } from'./resources';

declare module'react-i18next' {
 interface CustomTypeOptions {
 defaultNS:'common';
 resources: (typeof resources)['es'];
 }
}
