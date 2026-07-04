# GESLOC — Guía de despliegue

| Componente | Versión requerida |
|---|---|
| PHP | 7.4.x |
| PostgreSQL | 12.x |
| Nginx | 1.18+ |
| Composer | 2.x |
| Extensiones PHP | pdo_pgsql, ldap, mbstring, dom, xml, zip, gd |

- [ ] Copiar en laragon/www
- [ ] Crear el archivo de acceso en sites_enabled
- [ ] Cambiar `ldap_config.ini` a la del usuario de servicio real de AD
- [ ] Cambiar `db_config.ini` a la de la base de datos real
- [ ] Ejecutar `php install.php` y luego eliminarlo del servidor
- [ ] El index es login.html y se encuentra en public/pages/login.html
- [ ] Inicia sesión con LDAP usando un usuario del grupo `admin_group`.