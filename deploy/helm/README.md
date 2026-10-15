# Chart de maya-dms

Chart fino sobre [`maya-common`](https://github.com/Maya-AQSS/maya_platform/tree/main/charts/maya-common):
todo el despliegue (api, worker, scheduler, reverb, frontend, Job de migración,
Ingress, NetworkPolicy, Vault) vive en el library chart; aquí solo están los
valores de esta app (`values.yaml`).

- Imágenes: `10.224.237.240:5000/maya/maya-dms-{api,worker,reverb,frontend}:<tag>`.
- Publicación: el workflow `build-and-publish.yml` (reutilizable `build-app.yml` de
  maya_platform) empaqueta este chart con la versión de la release y lo sube a GHCR
  (`oci://ghcr.io/maya-aqss/charts/maya-dms`); IaC lo copia a la registry interna
  (`oci://registry.ceedcv/charts`) junto con las imágenes.
- Desarrollo: `IaC/.github/workflows/dev-deploy.yml` (K3s de desarrollo, `IaC/dev/PLAN.md`).
- Despliegue: `helmfile` en IaC (`prod/02-layer-sw`), con `MAYA_DMS_VERSION`.

Prueba local del render (necesita `helm dependency update` con acceso a GHCR,
o el chart maya-common copiado en `charts/`):

```bash
helm dependency update deploy/helm
helm template maya-dms deploy/helm -n maya-dms --set image.tag=1.0.0 | kubeconform -strict
```

## Comandos a mano en producción

Los datos de referencia no se cargan en el despliegue (el Job de migración solo migra).
Se lanzan a mano, una vez, con [`deploy/jobs/artisan-job.yaml`](../jobs/artisan-job.yaml):
un Job con la imagen *-worker de la versión desplegada, el ConfigMap y los secretos de Vault
de la app y el mismo `securityContext` que el Job de migración. Las instrucciones están en la
cabecera del fichero.

- `db:seed --class=ProductionSeeder --force`: mapa de procesos del CEEDCV y tema por defecto, sin
  usuarios de desarrollo. Idempotente: no pisa lo que se haya editado.
