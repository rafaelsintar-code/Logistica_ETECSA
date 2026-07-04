<?php
/**
 * LdapAuth — Autenticación contra Active Directory vía LDAP.
 *
 * Estrategia:
 *   1. Hace un bind inicial con el usuario de servicio (bind_dn) para
 *      buscar al usuario por su sAMAccountName.
 *   2. Intenta un segundo bind con las credenciales del usuario para
 *      validar su contraseña directamente en AD.
 *   3. Devuelve los atributos del usuario (cn, mail, sAMAccountName, etc.)
 *      si la autenticación es correcta, o lanza una excepción si falla.
 *
 * La autorización (rol) NO se gestiona aquí; se sigue usando la tabla
 * `usuario` de PostgreSQL como fuente de verdad para roles y estado activo.
 */
class LdapAuth
{
    private string $host;
    private int    $port;
    private string $baseDn;
    private string $bindDn;
    private string $bindPass;
    private string $loginAttr;
    private bool   $useTls;

    /** @var resource|false */
    private $conn = false;

    public function __construct()
    {
        $configFile = __DIR__ . '/../../../config/ldap_config.ini';

        if (!file_exists($configFile)) {
            throw new RuntimeException("Archivo ldap_config.ini no encontrado: {$configFile}");
        }

        $cfg = parse_ini_file($configFile, true);
        if ($cfg === false || empty($cfg['ldap'])) {
            throw new RuntimeException("Formato inválido en ldap_config.ini");
        }

        $l = $cfg['ldap'];
        $this->host      = getenv('LDAP_HOST')      ?: ($l['host']       ?? 'localhost');
        $this->port      = (int)(getenv('LDAP_PORT') ?: ($l['port']      ?? 389));
        $this->baseDn    = getenv('LDAP_BASE_DN')   ?: ($l['base_dn']    ?? '');
        $this->bindDn    = getenv('LDAP_BIND_DN')   ?: ($l['bind_dn']    ?? '');
        $this->bindPass  = getenv('LDAP_BIND_PASS') ?: ($l['bind_pass']  ?? '');
        $this->loginAttr = getenv('LDAP_ATTR')      ?: ($l['login_attr'] ?? 'sAMAccountName');
        $this->useTls    = filter_var(
            getenv('LDAP_USE_TLS') ?: ($l['use_tls'] ?? false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Autentica un usuario contra Active Directory.
     *
     * @param  string $username  El sAMAccountName (login) del usuario.
     * @param  string $password  Su contraseña en texto plano.
     * @return array  Atributos LDAP del usuario: cn, mail, sAMAccountName, dn.
     * @throws RuntimeException  Si la extensión LDAP no está disponible.
     * @throws InvalidArgumentException  Si las credenciales son incorrectas.
     */
    public function authenticate(string $username, string $password): array
    {
        if (!extension_loaded('ldap')) {
            throw new RuntimeException('La extensión PHP ldap no está habilitada.');
        }

        // Prevenir bind anónimo con contraseña vacía
        if (trim($password) === '') {
            throw new InvalidArgumentException('La contraseña no puede estar vacía.');
        }

        $this->connect();

        // ── Paso 1: bind con usuario de servicio ───────────────────────────
        $bound = @ldap_bind($this->conn, $this->bindDn, $this->bindPass);
        if (!$bound) {
            throw new RuntimeException(
                'No se pudo conectar al servidor LDAP con el usuario de servicio. '
                . 'Verifique bind_dn y bind_pass en ldap_config.ini.'
            );
        }

        // ── Paso 2: buscar el DN del usuario por sAMAccountName ────────────
        $filter   = "({$this->loginAttr}=" . ldap_escape($username, '', LDAP_ESCAPE_FILTER) . ")";
        $attrs    = ['dn', 'cn', 'mail', 'sAMAccountName', 'displayName', 'memberOf'];
        $search   = @ldap_search($this->conn, $this->baseDn, $filter, $attrs);

        if ($search === false || ldap_count_entries($this->conn, $search) === 0) {
            throw new InvalidArgumentException('Credenciales incorrectas.');
        }

        $entry   = ldap_first_entry($this->conn, $search);
        $userDn  = ldap_get_dn($this->conn, $entry);
        $attribs = ldap_get_attributes($this->conn, $entry);

        // ── Paso 3: bind con credenciales del usuario (valida la contraseña) ─
        $userBound = @ldap_bind($this->conn, $userDn, $password);
        if (!$userBound) {
            throw new InvalidArgumentException('Credenciales incorrectas.');
        }

        return [
            'dn'              => $userDn,
            'username'        => $attribs['sAMAccountName'][0] ?? $username,
            'nombre'          => $attribs['displayName'][0]    ?? ($attribs['cn'][0] ?? $username),
            'correo'          => $attribs['mail'][0]           ?? '',
            'memberOf'        => $this->parseGroups($attribs['memberOf'] ?? []),
        ];
    }

    /** Conecta al servidor LDAP y aplica opciones recomendadas para AD. */
    private function connect(): void
    {
        $this->conn = ldap_connect($this->host, $this->port);
        if (!$this->conn) {
            throw new RuntimeException("No se pudo conectar al servidor LDAP en {$this->host}:{$this->port}");
        }

        ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($this->conn, LDAP_OPT_NETWORK_TIMEOUT, 5);

        if ($this->useTls) {
            if (!@ldap_start_tls($this->conn)) {
                throw new RuntimeException('No se pudo iniciar TLS con el servidor LDAP.');
            }
        }
    }

    /** Extrae los nombres de los grupos de los DNs de memberOf. */
    private function parseGroups(array $memberOf): array
    {
        $groups = [];
        foreach ($memberOf as $key => $dn) {
            if ($key === 'count') continue;
            // Extrae el CN del primer segmento del DN
            if (preg_match('/^CN=([^,]+)/i', $dn, $m)) {
                $groups[] = $m[1];
            }
        }
        return $groups;
    }
}
