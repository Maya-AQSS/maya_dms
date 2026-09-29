# Chart de maya-dms

Chart fino sobre [`maya-common`](https://github.com/Maya-AQSS/maya_platform/tree/main/charts/maya-common):
todo el despliegue (api, worker, scheduler, reverb, frontend, Job de migración,
Ingress, NetworkPolicy, Vault) vive en el library chart; aquí solo están los
valores de esta app (`values.yaml`).

- Imágenes: `10.224.237.240:5000/maya/maya-dms-{api,worker,reverb,frontend}:<tag>`.
- Publicación: el workflow `build-and-publish.yml` empaqueta este chart con la
  versión de la release y lo sube a `oci://10.224.237.240:5000/charts/maya-dms`.
- Despliegue: `helmfile` en IaC (`prod/02-layer-sw`), con `MAYA_DMS_VERSION`.

Prueba local del render (necesita `helm dependency update` con acceso al registry,
o el chart maya-common copiado en `charts/`):

```bash
helm dependency update deploy/helm
helm template maya-dms deploy/helm -n maya-dms --set image.tag=1.0.0 | kubeconform -strict
```
