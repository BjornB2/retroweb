# Security

RetroWEB is intended for old browsers that often support only plain HTTP. It is
therefore not a secure channel and must not be used for accounts, payments or
sensitive information. The application blocks password fields, authentication,
uploads and several other unsafe request types, but that does not make an old
browser or an HTTP connection confidential.

Security issues in RetroWEB itself—such as SSRF bypasses, arbitrary file access,
command execution or proxy-limit bypasses—can be reported privately to
`bjorn@heirman.nl`. Please include the affected version, reproduction steps and
impact. Do not include real credentials or personal data.

There is no formal support window or guaranteed response time. Please avoid
publishing an unpatched vulnerability before there has been a reasonable
opportunity to investigate it.
