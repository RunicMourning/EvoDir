# EvoDir Security Audit Report

**Repository:** `RunicMourning/EvoDir`\
**Branch reviewed:** `main`\
**Audit type:** Source-code security review\
**Live exploitation:** Not performed; findings below are based on the
repository contents reviewed.

## Executive summary

EvoDir is not currently something I would call "wide open," but there
are several things I would fix before treating it as a reusable public
project.

The most important findings are:

1.  **High --- `login.php` contains a hard-coded administrator
    password.** If this file is reachable and the login is actually
    used, the credential is effectively public because the repository is
    public.
2.  **High --- `.includes` is hidden from the parent AutoIndex, but it
    is not itself protected from directory indexing.** `IndexIgnore`
    hides it from the parent listing; it does not make the directory
    inaccessible. With `Options +Indexes` inherited, `/.includes/` can
    potentially expose the internal file structure unless indexing is
    disabled there.
3.  **Medium --- the `descriptions` API accepts an arbitrary
    web-root-relative directory path.** I do not see a straightforward
    escape from the document root, so I am **not** calling this a
    classic path-traversal vulnerability. However, it lets callers ask
    the server to inspect arbitrary directories under the web root,
    including internal directories that were merely hidden from
    AutoIndex.
4.  **Medium --- the `stats` module exposes host/container information
    to unauthenticated callers when enabled.** This includes memory, CPU
    load, process count, uptime, mounted disks, and Docker/Plex state.
5.  **Medium --- weather fetching disables TLS certificate and hostname
    verification.** The URL is built from local configuration rather
    than user input, so this is not an obvious remote-code-execution
    path, but it removes transport authenticity from an external
    dependency.
6.  **Low/Medium --- several HTML-generation paths rely on `innerHTML`
    where safer DOM/text APIs could be used.** This is especially worth
    cleaning up because EvoDir is intended to consume directory names
    and administrator-controlled configuration.
7.  **Low --- several useful HTTP security headers are absent from the
    supplied `.htaccess`.**
8.  **Low --- the comments say caching is disabled only for theme CSS,
    but the actual `Header set` directives appear to apply globally.**
    This is primarily a configuration/behavior mismatch, not a direct
    security vulnerability.

There are also some things that I specifically **did not** flag as
vulnerabilities: the use of `IndexIgnore` itself is intentional and
reasonable; the `..` stripping in the current API is crude but appears
to prevent the obvious document-root escape; and the fixed
`shell_exec()` commands in the stats module are not command-injection
vulnerabilities because I found no user-controlled command string being
concatenated into them.

------------------------------------------------------------------------

## Findings

### EVD-001 --- Hard-coded administrator credential

**Severity: HIGH if `login.php` is deployed/used; otherwise
informational cleanup**

`/.includes/login.php` contains:

``` php
$valid_username = 'admin';
$valid_password = 'password123';
```

The repository is public, so anyone who can read the repository can
obtain the password. The login code also accepts the credential directly
and establishes the session.

Evidence: `.includes/login.php`.

### Why it matters

If this module is reachable at all, this is not merely a "weak
password." It is a **known credential embedded in distributed source
code**.

There are additional authentication weaknesses:

-   no password hashing;
-   no configurable credential source;
-   no rate limiting or lockout;
-   no visible `session_regenerate_id(true)` after successful login;
-   no visible CSRF protection;
-   no explicit secure/HttpOnly/SameSite session-cookie configuration in
    this file.

### Recommended fix

If `login.php` is obsolete, **delete it from the distributed project**.

If authentication is intended:

-   use PHP's `password_hash()` / `password_verify()`;
-   store the password hash outside the public web tree or in deployment
    configuration/environment;
-   regenerate the session ID after login;
-   add rate limiting;
-   configure session cookies securely;
-   add CSRF protection if the authenticated area performs
    state-changing actions.

------------------------------------------------------------------------

### EVD-002 --- `.includes` is hidden, not protected

**Severity: HIGH**

The root `.htaccess` contains:

``` apache
Options +Indexes
IndexIgnore .htaccess .includes .legal
```

`IndexIgnore` controls what Apache displays in a directory index; it
does **not** make the ignored directory inaccessible.

Apache documents `IndexIgnore` specifically as adding files to the list
hidden when displaying a directory index.

This is important because `.legal` already has its own:

``` apache
Options -Indexes
```

but `.includes` does not.

The `.includes` directory contains internal implementation files
including:

-   `api.php`
-   `evodir.conf`
-   `login.php`
-   `phpinfo.php`
-   `header.html`
-   `footer.html`
-   module directories
-   JavaScript/CSS assets

### Why it matters

Someone visiting the parent directory may not see `.includes`, but a
person who knows or guesses the URL can request it directly.

With directory indexing enabled globally, the question is not "can they
see `.includes` from `/`?" but "what happens when they request
`/.includes/`?"

### Recommended fix

At minimum, add:

``` apache
# .includes/.htaccess
Options -Indexes
```

That prevents directory enumeration while continuing to allow the files
EvoDir intentionally serves.

I would **not** blindly deny the entire directory because EvoDir
legitimately serves CSS, JavaScript, themes, API responses, and other
resources from it.

For genuinely private implementation files, consider moving them outside
the web root or explicitly denying those individual resources.

### About the blank-folder idea

A blank folder does **not** solve this particular problem. It may make a
listing less interesting, but it doesn't prevent someone from requesting
the directory or known files.

------------------------------------------------------------------------

### EVD-003 --- `descriptions` API can inspect arbitrary directories under the document root

**Severity: MEDIUM**

The API accepts:

``` php
$reqPath = isset($_GET['path']) ? $_GET['path'] : '/';
$reqPath = '/' . trim(str_replace('..', '', $reqPath), '/');
$absPath = rtrim($webroot . $reqPath, '/');
```

and then calls `is_dir()`, `scandir()`, `is_dir()`, `file_exists()`, and
`file_get_contents()` on the resulting path.

### Important distinction

I initially suspected this as a path-traversal vulnerability.

After examining the actual transformation, I **would not currently
report it as a successful `../` escape**. The application removes `..`
before constructing the filesystem path, which defeats the
straightforward traversal attempts.

However, the endpoint still gives an unauthenticated caller control over
**which directory inside the document root PHP inspects**.

That means a caller can potentially query paths that EvoDir
intentionally hides from the directory listing.

### Recommended fix

Don't construct filesystem paths from a browser-supplied path if you can
avoid it.

A stronger pattern is:

1.  derive the requested directory from the actual current request;
2.  canonicalize with `realpath()`;
3.  verify that the result is inside the document root;
4.  reject everything else.

For example:

``` php
$root = realpath($_SERVER['DOCUMENT_ROOT']);
$requested = realpath($root . '/' . ltrim($reqPath, '/'));

if (
    $requested === false ||
    ($requested !== $root && strpos($requested, $root . DIRECTORY_SEPARATOR) !== 0) ||
    !is_dir($requested)
) {
    http_response_code(404);
    exit;
}
```

That is much easier to reason about than string deletion.

------------------------------------------------------------------------

### EVD-004 --- Unauthenticated system-information disclosure through `stats`

**Severity: MEDIUM**

The `stats` module is enabled in the current configuration and exposes:

-   RAM usage;
-   swap usage;
-   CPU load;
-   process count;
-   uptime;
-   mounted filesystem names and capacity;
-   Docker running-container count;
-   Plex running/stopped status;
-   Plex memory usage.

The module is reached through the unauthenticated API router, so the
information is available to anyone able to call the API action.

### Why it matters

For a private homelab this may be exactly what you want.

For a **publicly reachable EvoDir installation**, it is useful
reconnaissance information:

-   confirms the server is Linux;
-   reveals approximate resource pressure;
-   exposes mount names;
-   reveals Docker usage;
-   reveals that Plex exists and whether it is running;
-   gives an attacker additional environmental context.

### Recommended fix

Make this explicitly configurable as a **public-information feature**,
rather than treating it as ordinary UI data.

For example:

``` ini
[MODULES]
stats|false
```

should be the safe default for a public installation.

Even better, distinguish between:

-   `stats_public`
-   `stats_private`

and require authentication for the latter.

------------------------------------------------------------------------

### EVD-005 --- TLS verification disabled for weather API

**Severity: MEDIUM**

The weather module uses cURL but explicitly disables both certificate
and hostname verification:

``` php
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
```

### Why it matters

This permits a man-in-the-middle to impersonate the weather service.

The impact is currently limited because:

-   the destination URL is generated from the server's configured
    coordinates;
-   the response is used for weather display;
-   the returned weather data does not appear to be used as executable
    server-side input.

So I do **not** see this as an immediate RCE path.

Nevertheless, disabling TLS verification should not be necessary for a
public project.

### Recommended fix

Remove both options and use normal certificate verification:

``` php
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
```

If a particular shared host has broken CA configuration, fix the CA
bundle rather than disabling verification.

------------------------------------------------------------------------

### EVD-006 --- HTML injection risk from banner configuration

**Severity: LOW/MEDIUM depending on who can edit configuration**

The banner module deliberately permits a subset of HTML:

``` php
$allowed = '<a><strong><em><u><b><i><br><p>';
```

and removes `on*=` attributes.

The client subsequently renders banner lines with:

``` javascript
p.innerHTML = line;
```

This is acceptable **if the configuration file is trusted**.

It becomes an XSS issue if an untrusted party can modify `evodir.conf`,
or if configuration is ever loaded from an administrator-facing web UI
without treating it as trusted input.

### Recommended fix

Prefer text APIs for ordinary text:

``` javascript
p.textContent = line;
```

If intentional HTML support is part of the feature, document it clearly
and use a real HTML sanitizer rather than a regex/`strip_tags()`
combination.

------------------------------------------------------------------------

### EVD-007 --- Directory listing data is rebuilt with `innerHTML`

**Severity: LOW**

The directory-listing rewrite parses Apache's generated HTML and later
constructs table rows with strings such as:

``` javascript
'<td class="col-name"><a href="' + e.href + '">' + e.label + '</a></td>'
```

This is fragile because `href` and label data originate from the
server-generated directory listing.

Apache's AutoIndex output normally performs HTML escaping, which reduces
the practical risk, but EvoDir then parses that HTML and reconstructs
another HTML document using string concatenation.

### Recommended fix

Build the cells with DOM APIs:

``` javascript
var link = document.createElement('a');
link.href = e.href;
link.textContent = e.label;
```

This eliminates an entire class of future escaping mistakes.

It also makes the code more robust if Apache output changes.

------------------------------------------------------------------------

### EVD-008 --- `phpinfo.php` is a dangerous leftover if reachable

**Severity: HIGH if publicly reachable; otherwise cleanup**

The repository contains `.includes/phpinfo.php`, a substantial
diagnostic/admin-style script that invokes `phpinfo()` and collects
server configuration, PHP modules, filesystem information, process
information, database availability, and other environment data.

The script even contains a secret-masking mechanism, which suggests it
was designed to be usable as a dashboard rather than merely being dead
test code.

I did not find evidence in the files reviewed that the existing login
module actually protects `phpinfo.php`.

### Recommendation

If this is an experiment/legacy diagnostic page:

**Delete it from the deployed build.**

If it is intended functionality:

-   put it behind real authentication;
-   do not expose it to unauthenticated visitors;
-   consider moving it outside the public web root;
-   avoid relying on keyword-based secret masking as the security
    boundary.

------------------------------------------------------------------------

## Configuration observations

### Cache-control directives are broader than their comment says

The `.htaccess` comment says:

> Disable caching for theme CSS files only

but the actual directives are:

``` apache
<IfModule mod_headers.c>
    Header set Cache-Control "no-store, no-cache, must-revalidate, max-age=0"
    Header set Pragma "no-cache"
    Header set Expires "0"
</IfModule>
```

There is no `<FilesMatch>` or other condition restricting them to theme
CSS.

Therefore, as written, these directives appear to apply broadly rather
than only to theme CSS.

This is not a security vulnerability, but it can hurt performance and is
worth correcting.

------------------------------------------------------------------------

## Things I explicitly checked and did NOT classify as vulnerabilities

### `IndexIgnore`

Your explanation was correct: this is a presentation/AutoIndex hiding
mechanism.

I am **not** counting it as a security control, but I am also not
calling its use a vulnerability.

### `..` handling in `api.php`

The current:

``` php
str_replace('..', '', $reqPath)
```

is not how I would write path validation, but I don't currently have
evidence that it provides a working escape from `DOCUMENT_ROOT`.

I would replace it with canonical-path validation because the
replacement is substantially easier to audit.

### `shell_exec()` in `stats`

The module uses commands such as:

``` php
cat /proc/meminfo
ps aux | wc -l
docker ps ...
```

These look scary at first glance, but the commands are constants rather
than strings assembled from user input.

Therefore I am **not** calling them command injection.

The issue with this module is information disclosure and unnecessary
host visibility, not command injection.

------------------------------------------------------------------------

# Recommended priority order

## Fix before public release

1.  **Remove or secure `login.php`.**
2.  **Remove or protect `phpinfo.php`.**
3.  **Add `.includes/.htaccess` with `Options -Indexes`.**
4.  **Decide whether `stats` is genuinely intended to be public.**
5.  **Restore TLS verification in the weather module.**
6.  **Replace the path-string manipulation with canonical `realpath()`
    validation.**

## Then harden

7.  Replace HTML string concatenation with DOM APIs where practical.
8.  Add security headers.
9.  Fix the cache-control scope.
10. Add authentication/authorization architecture before adding more
    administrative modules.

------------------------------------------------------------------------

# Suggested HTTP security headers

For a normal HTTPS deployment, a reasonable starting point is:

``` apache
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
</IfModule>
```

I would **not** blindly add a restrictive Content-Security-Policy yet
because EvoDir currently relies on inline scripts and dynamically loaded
scripts. CSP should be designed around EvoDir's actual script
architecture rather than bolted on as a generic header.

If the site is HTTPS-only, HSTS is also worth considering, but it should
only be enabled once you are certain the hostname will remain
HTTPS-only.

------------------------------------------------------------------------

# Overall assessment

**Current source-audit rating: Needs hardening, but not a disaster.**

The architecture is actually fairly understandable from a
security-review perspective: the API router is small, modules are
separated, the weather module does not accept arbitrary URLs, the banner
sanitizer exists, and the obvious `../` traversal attempt is blocked.

The biggest problems are more mundane:

> **There are several pieces of old/admin/test functionality sitting in
> a web-accessible directory, while Apache's global `Options +Indexes`
> makes "hidden" different from "protected."**

That is exactly the kind of thing I would clean up before calling EvoDir
a public reusable project.

## Scope limitation

This is a **source-code audit of the repository**, not a penetration
test of the running server. I have not attempted destructive requests,
authentication attacks, filesystem probing, or live exploitation against
the demo site.

The next useful phase would be a **safe live verification pass** against
a server you control, checking whether the findings above are actually
reachable in the deployed configuration. That would let us separate:

-   **code vulnerability**
-   **deployment vulnerability**
-   **dead/unused legacy code**
-   **intentional homelab behavior**

Those distinctions matter for EvoDir because some of the "sensitive"
functionality is clearly intended for a trusted homelab while other
pieces are meant to be safe for public sharing.
