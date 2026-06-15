{{/*
Nombre base del release.
*/}}
{{- define "maya-dms.fullname" -}}
{{- if .Values.fullnameOverride -}}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" -}}
{{- else -}}
{{- printf "%s-%s" .Release.Name (default .Chart.Name .Values.nameOverride) | trunc 63 | trimSuffix "-" -}}
{{- end -}}
{{- end -}}

{{- define "maya-dms.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" -}}
{{- end -}}

{{/*
Labels comunes.
*/}}
{{- define "maya-dms.labels" -}}
app.kubernetes.io/name: {{ include "maya-dms.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
helm.sh/chart: {{ printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end -}}

{{- define "maya-dms.selectorLabels" -}}
app.kubernetes.io/name: {{ include "maya-dms.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end -}}

{{/*
Imagen completa por componente.
*/}}
{{- define "maya-dms.backendImage" -}}
{{- printf "%s/%s/maya-dms-backend:%s" .Values.image.registry .Values.image.repository .Values.image.tag -}}
{{- end -}}

{{- define "maya-dms.frontendImage" -}}
{{- printf "%s/%s/maya-dms-frontend:%s" .Values.image.registry .Values.image.repository .Values.image.tag -}}
{{- end -}}

{{/*
Nombre del Secret (separado del ConfigMap por si se carga externamente).
*/}}
{{- define "maya-dms.secretName" -}}
{{- default (printf "%s-secret" (include "maya-dms.fullname" .)) .Values.secret.name -}}
{{- end -}}

{{- define "maya-dms.configMapName" -}}
{{- printf "%s-config" (include "maya-dms.fullname" .) -}}
{{- end -}}

{{- define "maya-dms.pvcName" -}}
{{- printf "%s-media" (include "maya-dms.fullname" .) -}}
{{- end -}}

{{/*
envFrom estándar (ConfigMap + Secret).
*/}}
{{- define "maya-dms.envFrom" -}}
- configMapRef:
    name: {{ include "maya-dms.configMapName" . }}
- secretRef:
    name: {{ include "maya-dms.secretName" . }}
{{- end -}}

{{/*
Variables extra que no van al ConfigMap (rol del contenedor, trustProxies).
*/}}
{{- define "maya-dms.extraEnv" -}}
- name: TRUSTED_PROXIES
  value: {{ .Values.trustProxies | quote }}
- name: TRUSTED_PROXY_CIDR
  value: {{ .Values.trustProxies | quote }}
{{- end -}}

{{/*
Volumes y volumeMounts para el media PVC (solo backend/worker).
*/}}
{{- define "maya-dms.mediaVolume" -}}
{{- if .Values.storage.enabled -}}
- name: media
  persistentVolumeClaim:
    claimName: {{ include "maya-dms.pvcName" . }}
{{- end -}}
{{- end -}}

{{- define "maya-dms.mediaVolumeMount" -}}
{{- if .Values.storage.enabled -}}
- name: media
  mountPath: {{ .Values.storage.mountPath | quote }}
  {{- if .Values.storage.subPath }}
  subPath: {{ .Values.storage.subPath | quote }}
  {{- end }}
{{- end -}}
{{- end -}}

{{/*
emptyDir para directorios escribibles (readOnlyRootFilesystem: true).
storage/logs incluido — con el daily log channel Laravel necesita escribir aquí
o el handler degrada silenciosamente / lanza excepción.
*/}}
{{- define "maya-dms.writableVolumes" -}}
- name: storage-framework
  emptyDir: {}
- name: storage-logs
  emptyDir: {}
- name: bootstrap-cache
  emptyDir: {}
- name: tmp
  emptyDir: {}
{{- end -}}

{{- define "maya-dms.writableVolumeMounts" -}}
- name: storage-framework
  mountPath: /var/www/html/storage/framework
- name: storage-logs
  mountPath: /var/www/html/storage/logs
- name: bootstrap-cache
  mountPath: /var/www/html/bootstrap/cache
- name: tmp
  mountPath: /tmp
{{- end -}}
