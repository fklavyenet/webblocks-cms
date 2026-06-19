# WebBlocks CMS Auth Standard

This standard documents the current package-owned CMS auth UI and session behavior for future CMS work. It is internal AI guidance, not public product documentation.

Implementation note: The WebBlocks Advisor gate was checked for this documentation cleanup on 2026-06-14 and for the coexistence auth route naming fix on 2026-06-19. This checkout does not expose an Advisor/knowledge Artisan command, so this standard is based on current package auth views, auth controllers, CMS mail support, coexistence route behavior, and README standards.

## Routes And Ownership

- CMS auth uses the package-owned `/webadmin/login`, forgot-password, and reset-password surfaces.
- CMS package auth routes must use CMS-owned route names. Use `webblocks.auth.login`, `webblocks.auth.login.store`, `webblocks.auth.logout`, `webblocks.auth.password.request`, `webblocks.auth.password.email`, `webblocks.auth.password.reset`, and `webblocks.auth.password.store`.
- CMS package views, controllers, middleware, and admin chrome must not depend on global `route('login')` or `route('logout')`, because a co-installed host product may own those names.
- The host `/login` model must be preserved. Do not introduce a separate mandatory duplicate CMS login system.
- CMS auth must not create duplicate users for the same email.
- The `App\Models\User` model remains app-owned. CMS access is authorized through CMS membership/role/access behavior on that user.
- Do not automatically equate host product admin status with CMS admin status.

## Guest Layout

- Auth screens must extend `webblocks-cms::layouts.guest`.
- The guest layout loads pinned WebBlocks UI CSS, icons CSS, pinned WebBlocks UI JavaScript, and `/cms/css/guest.css` when present.
- The normal auth shell is `div.wb-auth-shell.wb-auth-split`.
- The accent side uses `div.wb-auth-panel.wb-bg-primary`, the inline CMS brand mark, product name, and product slogan.
- The form side uses `div.wb-auth-form-area > div.wb-auth-card`.
- Use `wb-auth-header`, `wb-auth-body`, and `wb-auth-footer` for auth card structure.

## Login Form

- The login title is a compact welcoming heading, currently `Welcome back`.
- The form posts to `route('webblocks.auth.login')` with CSRF protection.
- Email uses `type="email"`, `autocomplete="username"`, `required`, `autofocus`, and `class="wb-input"`.
- Password uses `type="password"`, `autocomplete="current-password"`, `required`, and `class="wb-input"`.
- Password visibility toggle uses the existing `data-password-field`, `data-password-input`, and `data-password-toggle` hook pattern.
- Remember-me uses `label.wb-check` and the copy `Remember this device`.
- The forgot-password link uses `route('webblocks.auth.password.request')` when available.
- The primary submit button is a full-width WebBlocks primary button.

## Validation And Error Messages

- Session status renders as `wb-alert wb-alert-success`.
- Form-level validation errors render as `wb-alert wb-alert-danger` using the first error.
- Field-level errors render as `div.wb-field-error` and inputs set `aria-invalid="true"` plus `aria-describedby`.
- Inactive login accounts show a controlled validation error: `This account is inactive. Please contact an administrator.`
- Invalid credentials show the generic Laravel-style message: `The provided credentials do not match our records.`
- Forgot-password requests must not reveal whether an email belongs to a missing or inactive account.
- CMS password reset mail failures return a controlled CMS mail error and log only secret-safe context.

## Session And Token Behavior

- Login uses Laravel's `web` guard via `Auth::attempt($credentials, remember)`.
- Successful login regenerates the session and updates `last_login_at`.
- Successful login redirects to the intended URL or the admin dashboard.
- Logout uses a POST form to `route('webblocks.auth.logout')`, calls `Auth::guard('web')->logout()`, invalidates the session, regenerates the CSRF token, and redirects to `route('webblocks.auth.login')`.
- Password reset links use the CMS reset notification path with route name `webblocks.auth.password.reset`.
- Reset tokens are created through Laravel's password broker and must not be logged.
- Successful password reset hashes the new password, rotates `remember_token`, dispatches the password reset event, and redirects to login with status.

## Password Reset UI

- Forgot-password uses the same split auth shell and asks only for email.
- Reset-password uses hidden `token`, email, password, and password confirmation fields.
- Password reset form fields use WebBlocks form primitives and field-level error output.
- CMS-owned forgot-password mail may use environment mail settings or CMS custom mail settings through the CMS mail resolver, without changing host/root auth mail.

## Boundaries

- Do not add Tailwind, Vite, React, Vue, Livewire, Alpine-only behavior, or a separate auth frontend layer.
- Do not replace the split auth shell with custom panels unless the WebBlocks UI auth contract changes.
- Do not move app-owned user identity, install ownership, or host auth assumptions into reusable CMS core.
