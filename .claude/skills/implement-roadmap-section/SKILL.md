---
name: implement-roadmap-section
description: Implement one step or entity (e.g. S07, Brands) of the online pharmacy roadmap: read it, confirm the plan with examples, build, test, then ask before commit and push.
---

# Implement a roadmap section

Use this skill when the user asks to implement a step (`S00`–`S40`) or an entity/module (e.g. "Brands", "Coupons", "Zarinpal", "Checkout") of the online pharmacy project (Laravel + Filament 5 admin + Livewire 4/TallStackUI v4 storefront, MySQL, Redis, Horizon/Supervisor).

The argument is a step id or entity name. If none is given, ask which step to implement and suggest the next unfinished one from `ROADMAP_PROGRESS.md`.

## 1. Locate the roadmap
1. Look for `docs/pharmacy-roadmap.md` in the repository; otherwise search the repo for `pharmacy-roadmap.md`.
2. If it is missing, stop and ask the user to add it to `docs/` (it is the single source of truth). Do not implement from memory.
3. Read `ROADMAP_PROGRESS.md` (create it if missing with a table: step, title, status, date, commit).

## 2. Resolve the step
1. If the argument is a step id, find `### S{nn}` in Part E3.
2. If it is an entity or module name, look it up in the **E5 entity → step index**; if several steps match, ask the user which one.
3. Check dependencies: every earlier step in the same and previous phases (E2) should be `done`. If not, tell the user which are missing and ask whether to continue anyway.

## 3. Read everything the step references
Read the step's **Refs** sections fully, plus always: C3 (module structure), C4 (coding standards), D0 (rules for agents), D12.1 (schema conventions), D16 (security), D18 (testing), E1 (working agreement).
For **UI steps** (any storefront page/component or Filament screen) also read B2, B3, B7–B13 (design standards, competitor references, screen specs, component rules, admin rules, design QA checklist).
Then inspect the current codebase for what already exists in the affected module(s).

## 4. Clarify before coding (mandatory gate)
Send the user a short plan and wait for explicit approval. Use AskUserQuestion for decisions with options (recommended option first); put the plan itself in a message. The plan must include:
- Files, tables, routes, Filament resources and Livewire components to create or change.
- A concrete **example**: a sample DB row, a sample request/response, or a text sketch of the screen per breakpoint (mobile / tablet / desktop) for UI steps.
- Open decisions as questions with a recommended default.
- Packages to install (exact name + version constraint) and why; confirm compatibility with the project's Laravel/Filament/Livewire versions.
- The tests you will write.
Follow the example message in roadmap section E1. Do not write code until the user says yes. If the user changes something, update the plan and confirm again.

## 5. Implement
1. Create a branch: `git checkout -b feat/S{nn}-{slug}` from the latest `main` (ask before switching if the working tree is dirty).
2. Follow the module layout in C3: Models, Enums, Data (readonly DTOs), Actions, Queries, Events/Listeners/Jobs, Policies, Livewire, Filament/Resources, Database/{Migrations,Factories,Seeders}, Routes, Tests.
3. Rules: `declare(strict_types=1)`; money as integer Rial; grams/millimetres in DB; no business logic in Livewire/Filament classes (call Actions); a policy for every admin resource; no `$fillable` (models are written through setters and `save()`); secrets only via config/env; external APIs only through the module contracts (`ShippingCarrier`, `PaymentGateway`) and never real production endpoints.
4. UI: Persian/RTL text, only B3 tokens and B10 components (TallStackUI on the storefront, Filament defaults in admin), layouts per B9, image rules from D14.
5. Keep the change limited to the step; if you must change an earlier step's behaviour, say so and get approval.

## 6. Test (mandatory gate)
1. Write the Pest tests listed in the step plus any needed for new behaviour: unit (Actions, value objects), feature (routes, Livewire, Filament), arch (module boundaries), integration with `Http::fake()` for Tipax/Zarinpal.
2. Run, in order, and fix until all pass:
   - `vendor/bin/pint --test`
   - `vendor/bin/phpstan analyse`
   - `php artisan test --parallel`
3. For UI steps, go through the **B13 design QA checklist** and check the page at 375 px, 768 px and 1280 px if a browser is available; take screenshots for the report.

## 7. Report and ask to commit
1. Report: summary of changes (files by module), migrations added, test counts (passed/failed/skipped), B13 checklist result and screenshots for UI steps, manual checks left for the user (e.g. sandbox steps), and anything still marked [CONFIRM].
2. Ask: "Tests pass. Shall I commit with message `feat(<module>): <summary> (S{nn})`?" Commit only after an explicit yes. Use conventional commits and the attribution lines required by the environment.

## 8. Ask to push
Ask: "Shall I push `feat/S{nn}-{slug}` to origin (and open a pull request)?" Push or open the PR only after an explicit yes. Never force-push.

## 9. Record progress
Update `ROADMAP_PROGRESS.md`: step id, title, status `done`, date, commit hash (include this file in the commit, or in a follow-up commit after asking). Then suggest the next step.

## Never
- Skip the clarification, test, design QA, commit or push gates.
- Commit or push without the user's explicit approval in this conversation.
- Call real Tipax or Zarinpal production APIs, or put credentials in code, tests or commits.
- Mark a step done with failing tests.
