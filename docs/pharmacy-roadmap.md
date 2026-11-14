# Online Pharmacy: Business & Technical Roadmap

> **Purpose:** single source of truth for humans and AI agents implementing the online pharmacy (Livewire storefront + Filament admin).
> **Version:** 1.0 · 2026-11-06 (15 Aban 1405) · Owner: Mohammad Amiri, Nizek
> **How to use with AI:** run the `implement-roadmap-section` skill with a step id from **Part E** (e.g. `S07`). The agent reads that step and the sections it references, asks you to confirm with examples, implements, writes tests, runs them, and asks before commit and push.
> **[CONFIRM]** marks facts to verify with a vendor, lawyer or the live API before coding.

## Table of contents
- Part A: Business roadmap
- Part B: Product, design system, design standards, screen specifications and competitor insights (B1–B13)
- Part C: Architecture, stack and standards
- Part D: Data model, flows, routes, integrations, images, security, testing, DevOps, search engine (D21), reporting & warehouse (D22)
- Part E: Implementation steps (S00–S40), AI working agreement, entity → step index (E5) and the implementation skill (E6)
- Appendix: glossaries, env template, sources

---

# Part A: Business roadmap

## A1. Business model

We sell **cosmetics, personal care, supplements and medical devices** at launch, and **OTC medicines** after contracting a licensed partner pharmacy. We hold **no stock**: each paid order is bought from a provider (main: Sina's distribution company) at a discount, received at our warehouse, packed, and shipped by **Tipax** (other cities) or our **own courier** (home city). Customers pay online with **Zarinpal**.

**Unit economics per order**
```
gross_margin   = Σ(selling_price − provider_cost) × qty
net_margin     = gross_margin − (shipping_cost_actual − shipping_charged) − payment_fee − packaging_cost − coupon_discount
```
Medicines have government-set consumer prices: `selling_price ≤ official_price`. Margin on medicines comes only from the provider discount. Cosmetics and many supplements have free pricing.

**Revenue levers:** markup per category/brand, bundles, free-shipping threshold (raises basket size), near-expiry and damaged-box clearance, repeat purchases (refill reminders, phase 2).

## A2. Scope by phase

| Phase | Scope | Gate to start |
|---|---|---|
| **MVP (P1)** | Storefront (Livewire), admin (Filament), catalog, provider pricing, OTP login, cart, coupons, checkout, Zarinpal, Tipax + own courier, order sourcing/packing workflow, trust & help pages, SEO basics, image pipeline | e-Namad, business licence, Tipax contract, Zarinpal gateway |
| **P2** | OTC medicines via partner pharmacy, prescription upload + pharmacist queue, health magazine, BNPL (Snapp Pay / Torob Pay / Digipay), JSON API `/api/v1` (Sanctum) for mobile/tablet apps, refill reminders, city landing pages, wallet/points, ticketing, Meilisearch | Partner pharmacy contract; MVP stable 4 weeks |
| **P3** | Native/Flutter apps, PWA install, loyalty club, live chat, personalised recommendations, multi-provider auto-routing, lockers (Tipax kiosks) | P2 KPIs met |

## A3. Business roadmap (parallel to development)

| Week | Business task | Owner | Output |
|---|---|---|---|
| 1–2 | Register company, domain, business bank account | Founder | Legal entity |
| 1–3 | Legal consult on online health sales (IFDA rules, supplements classified as medicine, delivery of OTC) | Lawyer | Written opinion [CONFIRM] |
| 1–4 | Provider agreement with Sina's company: price list format (Excel), discount %, delivery to our warehouse, returns of damaged goods, payment terms | Founder | Signed agreement + sample price file |
| 2–4 | Tipax eTipax business account, contract, API key, pickup schedule, payment type (wallet/credit), COD | Ops | Test + production credentials |
| 2–5 | Zarinpal merchant registration, identity level ≥ silver, gateway on production domain | Finance | merchant_id |
| 3–6 | e-Namad application (needs live site with contact/about/terms) | Ops | e-Namad badge |
| 3–8 | Product data: top 300 SKUs with photos (1:1 white), weights, dimensions, IRC codes, descriptions | Content | Import file |
| 4–8 | Packaging: choose 4–6 carton sizes, map to Tipax packing ids | Ops | Box list |
| 6–9 | **Melli Payamak** account, documents, credit, API key, allowed server IP, 7 approved patterns (OTP + order events) | Ops | bodyIds |
| 8–10 | Soft launch to friends/family, 50 real orders | All | Bug list, cost data |
| 10–12 | Public launch, Instagram/Telegram campaign, Torob/Emalls listing | Marketing | Traffic |
| 12+ | Partner pharmacy contract → start P2 | Founder | Contract |

## A4. KPIs

| KPI | MVP target (first 3 months) |
|---|---|
| Conversion rate (sessions → orders) | ≥ 1.5 % |
| Average order value | ≥ 1,500,000 Toman |
| Gross margin | ≥ 22 % |
| Net margin after shipping/fees | ≥ 10 % |
| Paid → shipped time | ≤ 24 h (home city ≤ 4 h) |
| Order defect rate (wrong/damaged/late) | ≤ 2 % |
| Repeat customers within 60 days | ≥ 20 % |
| LCP (mobile, 4G) | < 2.5 s |

---

# Part B: Product, design system and competitor insights

## B1. What we take from each competitor, and how we improve it

| Site | What they do well (adopt) | Weakness | Our improvement |
|---|---|---|---|
| **Darunet** (darunet.com) | Deep mega-menu by body need; rich filters (brand, dosage form with counts, "suitable for", price); PDP with **expiry date**, ingredients table, usage, warnings, interactions, FAQ; BNPL instalment preview; pharmacist profile page | Loud pink brand colour; cluttered WooCommerce theme; very long category tree | Same filter depth but collapsible chips + bottom-sheet filters on mobile; calm teal palette; PDP tabs become sticky segmented control |
| **Darukade** (darukade.com) | Strongest trust: oldest licensed brand, licences page, magazine with **named expert authors and credentials**; express delivery band; quick "need" shortcuts | Long homepage with repetitive product rails | Short homepage, each widget capped at 6 items with "view all"; authors on articles in P2 |
| **Daroodrug** (daroodrug.com) | "Consult a doctor" CTA; daily deals with countdown; **bundles** (immunity pack); trust counters (cities served, orders, SKUs); returns guide | No instalments; deals mixed into many rails | Dedicated deals hub; bundle builder in admin; trust counters computed live |
| **Darochi** (darochi.com) | **Shop by nutrient** chips (Iron, B9, Zinc, Magnesium, C); quick view; variant picker (size/flavour); nutrition consultation; Snapp Pay rating as trust badge; city SEO pages | Prices in Rial (confusing); thin product content on cards | Always Toman in UI; nutrient chips as first-class **tags**; quick view as TallStackUI modal/slide |
| **Sarsoon** (sarsoon.com) | **Damaged-box (کج‌جعبه)** and **near-expiry (زودمصرف)** clearance sections; mobile OTP login; free shipping message; payment-methods page | Generic template; weak product imagery | Both clearance sections automated from variant flags + expiry date; badges on cards |
| **Mosbate Sabz** (mosbatesabz.com) | Most complete: discount tiers (10/20/30/50 %+), **hourly deals**, online prescription page, medical devices & home tests category, ticketing, scientific team page, licences page, post tracking code, cancellation/return policy | Dense UI, many banners | Same capabilities, cleaner layout, fewer banners, faster pages |

## B2. Design principles

1. **Mobile first**, app-like: bottom navigation (خانه، دسته‌ها، جستجو، سبد، حساب) on < 768 px; sticky search bar; swipeable chips.
2. **Search is the hero**: search field above the fold on every page, instant suggestions (products, brands, categories, tags).
3. **Trust on every product**: original-product badge, IRC code, expiry date, pharmacist name, return policy link.
4. **Calm health palette**, colour carries meaning: teal = brand/actions, coral = deals only, amber = near expiry, violet = prescription.
5. **One strong CTA per view**; secondary actions quiet.
6. **Consistent product cards**: 1:1 image on white, name (2 lines max), brand, price (old struck + new), discount badge, add-to-cart.
7. **Short homepage**: ≤ 8 widgets, each ≤ 6 items (decided 2026-11-07: every home widget, rail, slider and strip shows at most 6).
8. **Accessibility**: contrast ≥ 4.5:1, focus rings, 44×44 px touch targets, Persian `alt` texts, `lang="fa" dir="rtl"`.
9. **Persian typography**: Vazirmatn, Persian digits in UI, Jalali dates, Toman with thousands separators (۱٬۲۵۰٬۰۰۰ تومان).
10. **Performance budget**: ≤ 170 KB JS, ≤ 60 KB CSS (gzip), LCP < 2.5 s, CLS < 0.1, INP < 200 ms.

## B3. Colour palette and design tokens

| Token | Hex | Use |
|---|---|---|
| `primary-700` | `#0F766E` | Header, primary buttons, links |
| `primary-800` | `#115E59` | Button hover/pressed |
| `primary-50` | `#E6F6F3` | Chip backgrounds, info panels |
| `accent-500` | `#F97362` | Discounts, countdown, "add to cart" on deal cards only |
| `accent-600` | `#E85D4C` | Accent hover |
| `ink-900` | `#1E293B` | Main text |
| `ink-500` | `#64748B` | Secondary text |
| `line-200` | `#E2E8F0` | Borders, dividers |
| `surface-50` | `#F8FAFC` | Page background |
| `white` | `#FFFFFF` | Cards |
| `success-600` | `#16A34A` | In stock, success |
| `warning-500` | `#F59E0B` | Near expiry, warnings |
| `danger-600` | `#DC2626` | Errors, out of stock |
| `rx-600` | `#7C3AED` | Prescription badge |

Dark mode: not in MVP (admin uses Filament's built-in dark mode).

Tailwind v4 tokens (`resources/css/app.css`):
```css
@import "tailwindcss";
@theme {
  --font-sans: "Vazirmatn", ui-sans-serif, system-ui, sans-serif;
  --color-primary-50:  #E6F6F3;
  --color-primary-700: #0F766E;
  --color-primary-800: #115E59;
  --color-accent-500:  #F97362;
  --color-accent-600:  #E85D4C;
  --color-ink-900:     #1E293B;
  --color-ink-500:     #64748B;
  --color-line-200:    #E2E8F0;
  --color-surface-50:  #F8FAFC;
  --color-success-600: #16A34A;
  --color-warning-500: #F59E0B;
  --color-danger-600:  #DC2626;
  --color-rx-600:      #7C3AED;
  --radius-card: 0.75rem;
}
```

Typography scale (mobile / desktop): H1 24/32 px, H2 20/24, H3 17/20, body 15/16, small 13/14, line-height 1.7 for Persian body text. Spacing on a 4 px grid; cards `p-3`/`p-4`, radius 12 px, shadow `0 1px 2px rgb(15 23 42 / .06)`.

## B4. UI kits

- **Storefront:** **TallStackUI v4** (Livewire 4, Alpine 3, Tailwind 4). Use its components (button, input, select, modal, slide, dropdown, tab, badge, toast, dialog, loading, rating, number, pin for OTP). Customise via `TallStackUi::customize()` to the tokens above; set RTL.
- **Admin:** **Filament v5** panel at `/admin` with brand colour `#0F766E`, Vazirmatn font, RTL (Filament supports RTL with `fa` locale), dark mode allowed.
- Icons: Heroicons (bundled with both). Product category icons: SVG.
- Fonts self-hosted (Vazirmatn woff2, `font-display: swap`, preload 400/700).

## B5. Storefront pages (MVP)

| Route | Page | Key sections / components |
|---|---|---|
| `/` | Home | Built by the admin from widgets (D3.6 «Home widgets»): slider, collection rails, brands, categories, tags, trust; later flash deal and bundles (S33) |
| `/collections/{slug}` | Collection PLP | Same as PLP; `popular` is the system collection «محصولات محبوب» |
| `/categories/{slug}` | Category PLP | Breadcrumb, sub-category chips, filters (brand, form, suitable-for, price, in-stock, discount %), sort, infinite scroll/pagination, quick view |
| `/brands` and `/brands/{slug}` | Brands list, brand PLP | A–Z index, logo grid; brand header + PLP |
| `/categories`, `/collections`, `/collections/{slug}` | Categories list, collections list, collection PLP | Round icons / collection tiles; optional banner or slider on top (S13b). Addresses use full words (decided 2026-11-07, S13b); the first short ones `/b`, `/b/{slug}`, `/c/{slug}`, `/p/{slug}` redirect permanently (301). Tags stay at `/t/{slug}`. |
| `/t/{slug}` | Tag PLP (need/nutrient) | Same as PLP |
| `/search?q=` | Search PLP | Suggestions, synonyms, "did you mean", zero-result helper. The empty search box shows «محصولات محبوب» (D21.2) |
| `/products/{slug}` | Product detail (PDP) | Gallery + zoom, brand, badges, price, variant picker, expiry, stock, qty, add-to-cart, sticky buy bar (mobile), tabs (description, ingredients, usage, warnings, side effects, FAQ, reviews), related, ask a pharmacist |
| `/deals` | Deals hub | Flash deals, discount tiers, near-expiry, damaged-box |
| `/bundles/{slug}` | Bundle | Items, saving, add bundle |
| `/cart` | Cart | Items, coupon, free-shipping progress, summary |
| `/checkout` | Checkout (steps) | Address (map pin), shipping options (Tipax services / own courier + slot), review, pay |
| `/payment/zarinpal/callback` | Payment result | Success/failure, order number |
| `/login` | OTP login | Mobile → code (TallStackUI pin) |
| `/account` … | Account | Orders, order detail + tracking timeline, addresses, wishlist, reviews, profile |
| `/track` | Guest tracking | Order number + mobile |
| `/pages/{slug}` | Static pages | about, terms, privacy, returns, shipping, payment-methods, buying-guide, licences |
| `/faq`, `/contact` | FAQ, contact form | |
| `/pharmacist` | Pharmacist in charge | Name, licence number |

## B6. Admin (Filament) navigation

| Group | Resources / pages |
|---|---|
| Dashboard | Sales today/7d/30d, margin, orders by status, sourcing backlog, shipments in transit, low-margin alerts, failed integrations |
| Catalog | Products (variants as relation manager), Categories (tree), Brands, Attributes, Tags, Collections, Bundles, Reviews (moderation), Questions |
| Pricing | Providers, Provider prices, Pricing rules, Price imports (upload Excel, preview diff, apply) |
| Sales | Orders (state actions), Provider purchases, Returns, Coupons, Payments |
| Shipping | Shipments, Carrier services, Boxes, Origins, City mapping, Delivery zones & slots, Couriers, Shipping rules |
| Content | Home page builder («چیدمان صفحه اصلی»: widgets + slider banners), Flash deals, Pages, FAQ, Menus, Contact messages |
| Customers | Customers, Addresses (read-only), Stock alerts |
| Settings | General settings, Social links, Trust badges, Pharmacists, SEO redirects, Notification templates |
| System | Admin users, Roles & permissions, Activity log, Integration log, Horizon link, Health |

## B7. Design standards we follow

The storefront and admin must look professional, consistent and trustworthy. Every screen is built from the design system below; nothing is styled ad hoc.

| Standard | What it means for us |
|---|---|
| **WCAG 2.2 level AA** | Text contrast ≥ 4.5:1 (large text ≥ 3:1), visible focus ring, keyboard navigation, labels on every input, error text not by colour alone, `alt` on every image |
| **Touch targets** (Apple HIG 44 pt, Material 48 dp) | Every tappable element ≥ 44×44 px with ≥ 8 px between targets |
| **E-commerce UX research** (Baymard Institute guidelines) | Visible search, clear filters with applied-filter chips, prices and delivery cost shown early, guest-friendly cart, short checkout, no surprise fees, clear error recovery |
| **Core Web Vitals** (Google) | LCP < 2.5 s, CLS < 0.1, INP < 200 ms on mid-range Android over 4G |
| **RTL best practice** | Layout mirrors fully (`dir="rtl"`, logical CSS properties `ms-/me-/ps-/pe-`), directional icons mirrored (arrows, chevrons), numbers and Latin brand names stay LTR inside RTL text |
| **Design tokens** | Colours, spacing, radius, shadows, typography only from B3 tokens; no raw hex or arbitrary values in templates |
| **Atomic components** | Pages are composed of TallStackUI components and our wrapped Blade/Livewire components (B10); no one-off markup for buttons, inputs, badges, cards |

## B8. Design references from the six websites

What each site teaches, as a design pattern, and the professional standard we apply instead of copying its look.

### B8.1 Darunet (darunet.com)
- **Pattern to adopt:** deep "shop by body need" navigation; filter panel with counts per value (e.g. tablet (794), syrup (195)); PDP sections for ingredients table, usage, warnings, interactions, FAQ; expiry date near the price; instalment preview under the price.
- **Problems seen:** high-saturation pink (#FF318A) dominates; dense mega-menu with 80+ links at once; long text walls on PDP.
- **Our standard:** two-level mega-menu (max 8 columns × 8 links, "view all" per column); filters as collapsible groups with counts and a search inside long lists (brands); PDP long content in tabs with "read more" after 6 lines; instalment line in `ink-500` small text, never louder than the price.

### B8.2 Darukade (darukade.com)
- **Pattern to adopt:** trust-first footer (licences, founding year, responsible pharmacy, working hours, address, phone); "express delivery" strip at top; quick need chips under the hero; magazine authors shown with degree.
- **Problems seen:** long homepage with many similar product rails; repeated brand banners.
- **Our standard:** homepage ≤ 8 sections (B9.1); each rail has a clear title + "مشاهده همه" link; one announcement bar only (dismissible); trust block reused on footer, checkout and PDP.

### B8.3 Daroodrug (daroodrug.com)
- **Pattern to adopt:** "consult a doctor/pharmacist" entry point in the header; daily deal with countdown; ready-made bundles; live trust counters (cities served, orders, SKUs); clear shopping, shipping and returns guides in the footer.
- **Problems seen:** no instalments; deals scattered across the page.
- **Our standard:** one flash-deal module with countdown at a fixed position (home section 3); bundles use the same card anatomy with a "save X Toman" badge; trust counters as a 3-stat row with icons, computed live.

### B8.4 Darochi (darochi.com)
- **Pattern to adopt:** nutrient chips (Iron, B9, B7, Zinc, Magnesium, C); quick view on product cards; variant selection ("choose options") for size/flavour; nutrition consultation; payment-partner rating as a trust badge.
- **Problems seen:** prices in Rial with long numbers; cards with little hierarchy; "rating 0 of 5" shown on unrated products.
- **Our standard:** always Toman with Persian digits and thousands separators; hide rating when there are no reviews; variant picker as segmented buttons (≤ 5 options) or select (> 5); quick view as TallStackUI slide (mobile) / modal (desktop).

### B8.5 Sarsoon (sarsoon.com)
- **Pattern to adopt:** "damaged box" and "near expiry" clearance sections; free-shipping promise in the title; mobile-number login; payment-methods page.
- **Problems seen:** generic template look, inconsistent product photos.
- **Our standard:** clearance items carry a clear badge (amber "زودمصرف" with expiry date, gray "کج‌جعبه") and an explanation tooltip; all product photos 1:1 on white (D14.2); free-shipping progress bar in cart instead of a slogan.

### B8.6 Mosbate Sabz (mosbatesabz.com)
- **Pattern to adopt:** discount tiers (10/20/30/40/50 %, 50 %+); hourly deals; "newly added"; online prescription entry; medical devices and home tests categories; ticket support; scientific team and licences pages; tracking code lookup; cancellation and return policy page.
- **Problems seen:** visually dense, many competing banners and colours, long menus.
- **Our standard:** discount tiers as one chip row on the deals hub; sliders of ≤ 6 banners on home; accent coral used only for deals; support entry points grouped in one "help" menu.

## B9. Screen specifications (per breakpoint)

Breakpoints: mobile < 768 px, tablet 768–1279 px, desktop ≥ 1280 px. Content max width 1280 px (desktop), side gutters 16 px mobile, 24 px tablet, 32 px desktop. Grid: 4 columns mobile, 8 tablet, 12 desktop, gap 16 px.

### B9.1 Global header and navigation

| Element | Mobile | Tablet | Desktop |
|---|---|---|---|
| Announcement bar | 1 line, dismissible, 32 px | same | same |
| Header row | logo (right), cart + account icons (left), height 56 px | logo, search field (flex), cart, account, 64 px | logo, search (max 560 px), consult link, account, cart with count + total, 72 px |
| Search | full-width field under header, sticky on scroll | inline | inline |
| Category navigation | "دسته‌ها" tab in bottom nav → full-screen list (2 levels) | horizontal category bar under header | mega-menu on hover/click (B8.1 rule) |
| Bottom navigation | 5 items: خانه، دسته‌ها، جستجو، سبد خرید، حساب من (64 px, safe-area aware) | hidden | hidden |

### B9.2 Home (order of sections)
The home page is built by the admin from widgets (D3.6 «Home widgets», decided 2026-11-07); this is the suggested starting order. Every widget shows at most 6 items.

1. Hero slider (art-directed images, ≤ 6 banners, autoplay off on mobile, dots + swipe).
2. Need/nutrient chips (horizontal scroll, ≤ 6 chips).
3. Flash deal (countdown, 1 row of cards, "view all deals").
4. Collection rails, e.g. «محصولات محبوب» (≤ 6 cards + «مشاهده همه»).
5. Categories (round icons, ≤ 6), e.g. بانوان، آقایان، کودکان، مادر و نوزاد.
6. Bundles (rail).
7. Brands (round logos, ≤ 6, grayscale → colour on hover).
8. Trust block (licences, pharmacist, delivery, returns; 4 icons) + trust counters.

Rails: mobile shows 2.3 cards (peek), tablet 4, desktop 5–6; arrows on desktop only.

### B9.3 Category / brand / tag / search PLP

| Element | Mobile | Tablet | Desktop |
|---|---|---|---|
| Title + count | top | top | top |
| Sub-category chips | horizontal scroll | same | same |
| Filters | "فیلترها" button → bottom slide (full height), apply/clear buttons sticky | left/right drawer | sidebar 280 px (right side in RTL), groups collapsible |
| Applied filters | chips row with × and "حذف همه" | same | same |
| Sort | select in toolbar | same | segmented links |
| Grid | 2 columns | 3–4 columns | 4 columns (with sidebar) |
| Pagination | infinite scroll with "load more" fallback | same | numbered pages + infinite optional |

### B9.4 Product card anatomy (all breakpoints)
1. Image 1:1 on white, dominant-colour placeholder, max 1 badge top corner (priority: discount % → near-expiry → damaged-box → new).
2. Brand (small, `ink-500`, 1 line).
3. Name (2 lines max, ellipsis, 15 px, `ink-900`).
4. Rating stars + count (only if ≥ 1 review).
5. Price block: old price struck (`ink-500`, 13 px) above new price (bold, 16 px), "تومان" suffix.
6. Action: "افزودن به سبد" icon button (44×44); becomes − qty + stepper after adding.
Card: white, radius 12 px, border `line-200`, hover shadow on desktop; whole card clickable except the action.

### B9.5 Product detail page (PDP)

| Element | Mobile | Tablet | Desktop |
|---|---|---|---|
| Layout | single column | 2 columns 50/50 | gallery 5/12, info 4/12, buy box 3/12 (sticky) |
| Gallery | swipe carousel, dots, tap → full-screen zoom | thumbnails below | vertical thumbnails beside, hover zoom |
| Info | brand, name (H1), rating, badges (original, IRC code, rx/near-expiry), short description, key attributes (form, count, suitable for) | same | same |
| Buy box | price, variant picker, expiry date, stock, qty, delivery estimate for user's city, instalment line, add-to-cart | same | sticky card |
| Sticky buy bar | bottom bar: price + add-to-cart (appears after scrolling past buy box) | same | not needed (sticky buy box) |
| Tabs | segmented control, sticky under header: توضیحات، ترکیبات، نحوه مصرف، هشدارها، پرسش‌ها، نظرات | same | same |
| Below | ask a pharmacist, related products rail, recently viewed | same | same |
| Rx product (MVP) | add-to-cart replaced by info box "فروش آنلاین این دارو به‌زودی" (violet) | same | same |

### B9.6 Cart
Mobile: item list (image 64, name, variant, price, qty stepper, remove), coupon field (collapsible), free-shipping progress bar, summary card, sticky "ادامه خرید" button. Desktop: items 8/12, summary 4/12 sticky. Empty state: illustration + "سبد خرید شما خالی است" + popular categories.

### B9.7 Checkout (3 steps + pay)
- Stepper on top: آدرس ← ارسال ← بازبینی و پرداخت.
- Step 1 Address: saved addresses as radio cards; "new address" opens form with map pin (slide on mobile, modal on desktop).
- Step 2 Shipping: options as radio cards: icon, service name (e.g. "تیپاکس، ارسال یک‌روزه"), delivery estimate, price ("رایگان" in success colour if free). Own-courier option shows time-slot chips.
- Step 3 Review: items summary (collapsed on mobile), address, shipping, coupon, totals, terms checkbox, "پرداخت" primary button → Zarinpal.
- Desktop: form 8/12, sticky order summary 4/12. Mobile: summary collapsed at top, sticky pay button.
- Never show fees for the first time at the last step.

### B9.8 Payment result and order tracking
- Success: check icon, order number (copy button), next steps, "پیگیری سفارش".
- Failure: clear reason (from Zarinpal code → Persian), "تلاش دوباره" (same order), support link.
- Tracking timeline: vertical steps (ثبت شد، پرداخت شد، در حال تأمین، بسته‌بندی شد، تحویل به تیپاکس، در مسیر، تحویل شد) with Jalali date-time; Tipax tracking code with copy + link.

### B9.9 Account
Mobile: list menu (orders, addresses, wishlist, reviews, profile, logout). Desktop: sidebar menu 3/12 + content 9/12. Order list cards: number, date, status badge, total, thumbnail stack.

### B9.10 Login (OTP)
Single centered card (max 400 px): logo, title "ورود یا ثبت‌نام", mobile field (numeric keyboard, Persian/Latin digits accepted), primary button; step 2: 5-box pin input, countdown "ارسال مجدد کد ۰۱:۵۹", edit-number link. Terms note under the button.

## B10. Component rules (design system)

| Component | Variants | Rules |
|---|---|---|
| Button | primary (teal fill), secondary (teal outline), ghost, danger, deal (coral, deals only) · sizes sm 36 / md 44 / lg 52 px | One primary per view; loading state with spinner and disabled; icon + text where meaning is not obvious |
| Input / select / textarea | default, error, disabled | Label above (never placeholder-only), helper text below, error text in `danger-600` with icon |
| Badge | discount (coral), near-expiry (amber), damaged-box (gray), rx (violet), original (teal), new (ink) | Max 1 badge on cards, max 3 on PDP |
| Price | regular, discounted, free | Always Toman, Persian digits, thousands separator; discounted shows old struck + % badge |
| Card | product, bundle, category tile, info | Radius 12, border `line-200`, padding 12/16 |
| Chip | filter, tag, applied-filter (with ×) | Height 36, radius full, selected = teal fill + white text |
| Toast | success, error, info | TallStackUI toast, top-center mobile, top-left desktop (RTL), 4 s |
| Modal / slide | modal (desktop), slide from bottom (mobile) | Title, close button, primary action at the end; trap focus |
| Empty state | cart, search, orders, wishlist | Icon/illustration + one sentence + one action |
| Skeleton | card, list row, PDP | Match final layout size exactly (no CLS) |
| Stepper | checkout | Current, done (check), upcoming |
| Countdown | flash deal | `HH:MM:SS` Persian digits, coral |

Icons: Heroicons outline 24 px (20 px in dense UI), stroke 1.5; mirrored when directional. Illustrations: one consistent flat style, teal/mint palette.

## B11. Content, imagery and motion
- **Microcopy:** short, polite Persian (شما form), active voice, no ALL-CAPS Latin, no exclamation marks in errors. Buttons say the action ("افزودن به سبد", not "تأیید").
- **Numbers & dates:** Persian digits, Jalali dates (۱۵ آبان ۱۴۰۵), Toman.
- **Product photos:** 1:1, pure white background, product fills ~80 % of the frame, no text on photo, front view first.
- **Banners:** one message + one CTA, text ≤ 7 words, text kept in the safe area (center 70 %) of each art-direction size, no tiny legal text inside images.
- **Motion:** 150–250 ms ease-out; respect `prefers-reduced-motion`; no autoplay video; skeleton shimmer only while loading.

## B12. Admin (Filament) design rules
- Use Filament's default components and spacing; only the brand colour (`#0F766E`), font (Vazirmatn) and logo are customised.
- Tables: first column = primary identifier (name/number), status as badge with consistent colours (same mapping as storefront), money columns right-aligned in Toman, dates Jalali, max 7 visible columns (others toggleable), filters for status/date/brand/category, bulk actions only where safe and **never bulk delete** (decided 2026-11-07: records are deleted one at a time so each delete runs its own checks).
- Forms: grouped in sections/tabs in this order: General → Content → Pricing/Inventory → Media → SEO; required fields marked; helper text for business rules (e.g. "قیمت نهایی نمی‌تواند از قیمت مصوب بیشتر باشد").
- Order view: status timeline on top, actions as header buttons with confirmation modals, items table with cost and margin (visible to `super_admin` only).
- Dashboard: 4 KPI stats on top, then charts (sales/margin 30 days), then work queues (sourcing backlog, packing queue, failed integrations).

## B13. Design QA checklist (every UI step must pass)
- [ ] Only B3 tokens used (no raw hex, no arbitrary spacing); only B10 components.
- [ ] Mobile (375), tablet (768) and desktop (1280) layouts match B9.
- [ ] RTL correct: alignment, icons mirrored, numbers readable.
- [ ] Contrast ≥ 4.5:1; focus visible; all inputs labelled; images have Persian alt.
- [ ] Touch targets ≥ 44 px; bottom nav does not cover content (safe-area padding).
- [ ] Loading (skeleton), empty, error and success states designed.
- [ ] Prices in Toman with Persian digits; Jalali dates.
- [ ] No layout shift when images load (width/height or aspect-ratio set).
- [ ] Lighthouse mobile ≥ 90 Performance, ≥ 95 Accessibility on the changed page.
- [ ] Screenshots of the three breakpoints shown to the user before commit.


---

# Part C: Architecture, stack and standards

## C1. Stack and versions

| Layer | Choice | Version rule |
|---|---|---|
| Language | PHP | **8.5** (`declare(strict_types=1)` everywhere) |
| Framework | Laravel | **13.x** if Filament 5 and all packages below support it at install time, otherwise **12.x** [CONFIRM in S00] |
| Admin | Filament | **v5** (Livewire 4, Tailwind 4; same features as v4) |
| Storefront | Livewire **4** + Alpine **3** + Tailwind CSS **4** (TALL) | |
| UI kit | **TallStackUI v4** (`tallstackui/tallstackui:^4.0`) | requires Livewire 4, Tailwind 4 |
| Database | **MySQL 8.4 LTS**, InnoDB, `utf8mb4_unicode_ci` (or `utf8mb4_persian_ci` for Persian sort) | |
| Cache / queue / session | **Redis 7** | separate DB indexes: 0 cache, 1 queue, 2 session |
| Queue workers | **Laravel Horizon** run by **Supervisor** | |
| Web server | Nginx + PHP-FPM 8.5 (or FrankenPHP/Octane in P3) | |
| Search | Laravel Scout `database` driver (MySQL FULLTEXT) in MVP; self-hosted Meilisearch with hybrid search + local embeddings (Ollama `bge-m3`) in P2 (D21) | pin Meilisearch version at install |
| Images | spatie/laravel-medialibrary + spatie/image (Imagick with AVIF/WebP support) + libvips optional | |
| CDN | ArvanCloud CDN in front of object storage (S3-compatible, e.g. ArvanCloud Object Storage) | |
| Tests | **Pest 4** (+ Livewire, Filament testing helpers, Pest browser plugin) | |
| Static analysis | Larastan (PHPStan) level 8, Laravel Pint, Rector | |

## C2. Packages

| Package | Purpose | Module |
|---|---|---|
| `filament/filament:^5.0` | Admin panel | Admin |
| `livewire/livewire:^4.0` | Storefront components | Storefront |
| `tallstackui/tallstackui:^4.0` | UI components | Storefront |
| `spatie/laravel-permission` | Roles & permissions | Identity |
| `bezhansalleh/filament-shield:^4.3` | Roles & permissions UI for Filament (decided 2026-11-06; v4.3 supports Filament ^4/^5 and spatie/laravel-permission ^6–^8), https://filamentphp.com/plugins/bezhansalleh-shield | Identity |
| `spatie/laravel-medialibrary` | Media, conversions, responsive images | Media |
| `srwiez/thumbhash` | ThumbHash placeholders | Media |
| `filament/spatie-laravel-media-library-plugin` | Admin image upload fields (preview, replace, remove) | Media |
| `rhukster/dom-sanitizer` | Sanitizes uploaded SVG (MIT) | Media |
| `spatie/laravel-activitylog` | Admin activity log | System |
| `spatie/laravel-sluggable` | Slugs (Persian-safe slugger configured) | Shared |
| `spatie/laravel-settings` | Typed site settings | Settings |
| `spatie/laravel-sitemap` | sitemap.xml | Seo |
| `spatie/laravel-backup` | DB + media backups | System |
| `spatie/laravel-health` | Health checks (DB, Redis, queue, disk, Tipax, Zarinpal) | System |
| `spatie/laravel-csp` or `bepsvpt/secure-headers` | Security headers / CSP | Security |
| `hekmatinasser/verta` (or `morilog/jalali`) | Jalali dates | Shared |
| `laravel/horizon` | Queue dashboard & workers | System |
| `laravel/pulse` | Production performance monitoring | System |
| `laravel/telescope` | Local debugging only | Dev |
| `laravel/scout` | Search abstraction | Search |
| `meilisearch/meilisearch-php:^1.17` | Meilisearch client, used from the MVP (decided 2026-11-07 in S12); guzzlehttp/psr7 already provides the HTTP factories, so `http-interop/http-factory-guzzle` is not needed | Search |
| `swiper` (npm, pinned `14.3.0`) | Home page sliders: banners and, when their items do not fit, the collection, brand, category, tag and trust rows; touch, pen and mouse swipe in every browser incl. Firefox for Android (decided 2026-11-09, S13b) | Content |
| `openspout/openspout:^4.32` | Excel/CSV reading and writing: search synonyms import (S12), provider price import (S14) | Search, Pricing |
| `mews/purifier` or Symfony HtmlSanitizer | Sanitize rich text | Shared |
| `sentry/sentry-laravel` | Error tracking | System |
| `melipayamak/php` (optional) or own HTTP client | Melli Payamak SMS (D11.6) [CONFIRM package] | Notifications |
| `pestphp/pest`, `pestphp/pest-plugin-laravel`, `pestphp/pest-plugin-livewire`, `pestphp/pest-plugin-browser` | Tests | Dev |
| `larastan/larastan`, `laravel/pint`, `rector/rector` | Quality | Dev |
| `barryvdh/laravel-debugbar` | Local only | Dev |

Rule: every package is added in the step that first needs it, after asking the user (Part E working agreement). Check each package's support for the chosen Laravel/Filament/Livewire versions before installing.

## C3. Modular architecture (internal modules)

```
app/
  Modules/
    Shared/        # base classes, money, Jalali, slugger, value objects, traits
    Identity/      # admins, roles, customers, OTP
    Locations/     # provinces, counties, cities, regions, neighbourhoods
    Catalog/       # categories, brands, products, variants, attributes, tags, bundles, reviews, questions
    Media/         # image presets, conversions, thumbhash
    Pricing/       # providers, provider prices, rules, imports, pricing engine
    Cart/          # cart, coupons
    Checkout/      # checkout orchestration
    Orders/        # orders, items, state machine, sourcing, returns
    Payments/      # gateways (Zarinpal), payments, refunds, reconciliation
    Shipping/      # carriers (Tipax, own courier), boxes, quotes, shipments, zones
    Content/       # banners, home sections, flash deals, pages, FAQ, menus, contact
    Settings/      # settings, social links, trust badges, pharmacists
    Seo/           # redirects, sitemap, meta
    Search/        # search index, logs, synonyms
    Notifications/ # SMS sender, templates, logs
    System/        # integration logs, health, activity
    Reporting/     # rpt_* warehouse tables, builders, dashboard widgets, report pages (D22)
```

Each module has the same shape:
```
app/Modules/Catalog/
  CatalogServiceProvider.php     # registers routes, migrations, views, Livewire components, policies, events
  Models/                        # Eloquent models
  Enums/                         # backed enums (ProductType, ProductStatus …)
  Data/                          # readonly DTOs
  Actions/                       # single-purpose use cases: CreateProduct, PublishProduct …
  Queries/                       # read models / query objects for PLP, PDP
  Events/  Listeners/  Jobs/
  Policies/
  Http/Controllers/              # thin: Form Request → DTO → Action → Resource (webhooks, callbacks, AJAX, P2 API)
  Http/Requests/                 # Form Requests with baseRules(), prepareForValidation(), toDto()
  Http/Resources/                # API Resources for every JSON response
  Livewire/Forms/                # Livewire Form objects reusing baseRules(), exposing toDto()
  Livewire/                      # storefront components of this module
  Filament/Resources/            # admin resources of this module
  Database/Migrations/  Database/Factories/  Database/Seeders/
  Routes/web.php
  resources/views/
  Tests/Unit/  Tests/Feature/
```

Rules:
1. A module may call another module **only through its Actions, Queries, Events or Contracts**, never through another module's models directly (except reading foreign keys). Enforced with Pest `arch()` tests.
2. Cross-module side effects use **events** (e.g. `Orders\Events\OrderPaid` → Shipping, Notifications, Pricing listen).
3. Each module registers its own Filament resources: in `AdminPanelProvider`, `->discoverResources(in: app_path("Modules/{$m}/Filament/Resources"), for: "App\\Modules\\{$m}\\Filament\\Resources")` for each module.
4. Each module loads its migrations: `$this->loadMigrationsFrom(__DIR__.'/Database/Migrations')`.
5. Livewire components are registered under the module's Livewire 4 namespace (the module name in kebab case), e.g. `catalog::product-page` → class `App\Modules\Catalog\Livewire\ProductPage`, or a single-file `⚡product-page.blade.php` in the module's `resources/views/livewire`. (Not `catalog.product-page`: in Livewire 4 a dot means a sub-folder.) Module Blade views use the same prefix: `view('catalog::...')`.
6. Composer PSR-4 already covers `App\\` → `app/`; tests under `app/Modules/*/Tests` are added to `phpunit.xml` test suites.

## C4. Coding standards

- `declare(strict_types=1);` in every PHP file; `final` classes by default; `readonly` DTOs.
- PHP 8.5 features where they help readability: pipe operator `|>` for simple transform chains, `array_first()`/`array_last()`, `#[\NoDiscard]` on Actions that return results, property hooks and asymmetric visibility (8.4) on DTOs/value objects.
- Money: integer **Rial** everywhere (`Money` value object in Shared), formatted as Toman in views via a Blade helper `@toman($rial)`.
- Units: grams, millimetres in DB; convert at integration boundary.
- Enums: backed string enums with `label(): string` returning Persian text.
- **Models** (decided 2026-11-06): every column has a constant `COLUMN_<NAME>` (e.g. `public const string COLUMN_REGION_ID = 'region_id';`), and every column has a typed getter and setter (`getRegionId(): ?int`, `setRegionId(?int $regionId): static`); relations have getters too (`getRegion(): ?Region`). Application code uses the constants, getters and setters instead of string column names or magic properties, so every use of a column is traceable. Migrations keep literal column names (they are fixed snapshots of the schema). Models declare **no `$fillable`** (decided 2026-11-07): they stay fully guarded (Laravel's default), and every write goes through the setters and `save()`; never `create([...])`, `update([...])` or `fill()` with arrays (factories and query-builder upserts are fine).
- No business logic in controllers, Livewire components or Filament resources: they build a **DTO** and call an **Action** (C5).
- Validation in **Form Requests** (controllers), Livewire Form objects and Filament field rules that all reuse the Form Request's `baseRules()`; domain checks in Actions.
- Every JSON response goes through an **API Resource**.
- DB transactions in Actions that write several tables; `lockForUpdate()` for payments, stock-like counters (flash deal sold count, coupon usage).
- Config via `config/*.php` + `.env`; never `env()` outside config files.
- Pint preset `laravel`, Larastan level 8 must pass in CI.
- Conventional commits: `feat(catalog): add brands resource`, `fix(payments): handle code 101`.
- Branch per step: `feat/S07-brands`.

## C5. Request → DTO → Action → Resource pattern (mandatory)

Every write operation and every JSON response follows one path. No exceptions without the user's approval.

```
HTTP request ──▶ Form Request (validation + authorization)
             ──▶ $request->toDto()  ──▶ readonly DTO (typed, normalised)
             ──▶ Action::__invoke(DTO)  (business logic, transaction, events)
             ──▶ Model / result DTO
             ──▶ API Resource (JSON)  or  redirect / view
```

Rules:
1. **Controllers are thin**: resolve the Form Request, build the DTO with `$request->toDto()`, call one Action, return a Resource/redirect. No queries, no business rules, no `Model::create($request->all())`.
2. **Validation lives in Form Requests** (`app/Modules/{Module}/Http/Requests`): `authorize()` uses policies; `rules()` returns explicit rules; `prepareForValidation()` normalises input (Persian/Arabic digits → Latin, mobile `+98` → `09…`, trims, Persian letters via `PersianNormalizer`). Never validate inside Actions what the request already validates; Actions only enforce **domain** rules (e.g. coupon still valid, price ≤ official price) and throw domain exceptions.
3. **DTOs** (`app/Modules/{Module}/Data`) are `final readonly` classes with typed constructor properties, money as `int` Rial, enums as enum types, nested DTOs for nested data, and named constructors: `fromRequest()` (or the request's `toDto()`), `fromArray()` (Livewire/Filament/jobs), `fromModel()` when needed. No Eloquent models or `Request` objects inside DTOs.
4. **Actions** (`app/Modules/{Module}/Actions`) are `final` invokable classes, one use case each, **accept a DTO** (plus at most an authenticated actor or a model being acted on) and return a model or a result DTO. They own transactions, locking, events and logging. Actions are the only place that writes to the database for that use case.
5. **API Resources** (`app/Modules/{Module}/Http/Resources`) shape every JSON response: autocomplete, AJAX endpoints, webhook acknowledgements that return data, and the P2 `/api/v1`. Never return models or arrays straight from a controller. Money in resources is returned as Rial `int` plus a formatted Toman string; dates as ISO-8601 plus Jalali string.
6. **Livewire and Filament use the same Actions and DTOs**: Livewire components validate with **Livewire Form objects** that reuse the Form Request's `rules()` and expose `toDto()`; Filament pages override `handleRecordCreation()` / `handleRecordUpdate()` (or action `->action()`) to build the DTO from form data and call the Action. Business logic is never duplicated between controller, Livewire and Filament.
7. **Tests**: each Form Request (rules + normalisation), each DTO (construction/mapping), each Action (behaviour, transaction, events) and each Resource (JSON shape) has tests.

### C5.1 Folder layout per module
```
app/Modules/Cart/
  Http/
    Controllers/ApplyCouponController.php
    Requests/ApplyCouponRequest.php
    Resources/CartResource.php
  Data/ApplyCouponData.php
  Actions/ApplyCoupon.php
  Livewire/Forms/ApplyCouponForm.php
```

### C5.2 Example: Form Request with `toDto()`
```php
<?php

declare(strict_types=1);

namespace App\Modules\Cart\Http\Requests;

use App\Modules\Cart\Data\ApplyCouponData;
use App\Modules\Shared\Support\PersianNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class ApplyCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('cart')) ?? false;
    }

    public static function baseRules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9\-]+$/'],
        ];
    }

    public function rules(): array
    {
        return self::baseRules();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper(PersianNormalizer::digits(trim((string) $this->input('code')))),
        ]);
    }

    public function toDto(): ApplyCouponData
    {
        return new ApplyCouponData(
            cartId: (int) $this->route('cart')->id,
            code: $this->validated('code'),
            customerId: (int) $this->user()->id,
        );
    }
}
```

### C5.3 Example: DTO
```php
<?php

declare(strict_types=1);

namespace App\Modules\Cart\Data;

final readonly class ApplyCouponData
{
    public function __construct(
        public int $cartId,
        public string $code,
        public int $customerId,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            cartId: (int) $data['cart_id'],
            code: (string) $data['code'],
            customerId: (int) $data['customer_id'],
        );
    }
}
```

### C5.4 Example: Action
```php
<?php

declare(strict_types=1);

namespace App\Modules\Cart\Actions;

use App\Modules\Cart\Data\ApplyCouponData;
use App\Modules\Cart\Exceptions\CouponNotApplicable;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\Coupon;
use Illuminate\Support\Facades\DB;

final class ApplyCoupon
{
    public function __construct(private readonly ValidateCoupon $validateCoupon) {}

    #[\NoDiscard]
    public function __invoke(ApplyCouponData $data): Cart
    {
        return DB::transaction(function () use ($data): Cart {
            $cart   = Cart::query()->lockForUpdate()->findOrFail($data->cartId);
            $coupon = Coupon::query()->where('code', $data->code)->first()
                ?? throw CouponNotApplicable::notFound($data->code);

            ($this->validateCoupon)($coupon, $cart, $data->customerId); // domain rules, throws on failure

            $cart->coupon()->associate($coupon)->save();

            return $cart->refresh()->load('items.variant', 'coupon');
        });
    }
}
```

### C5.5 Example: thin controller + API Resource
```php
final class ApplyCouponController
{
    public function __invoke(ApplyCouponRequest $request, ApplyCoupon $applyCoupon): CartResource
    {
        $cart = $applyCoupon($request->toDto());

        return CartResource::make($cart);
    }
}
```
```php
final class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'items'       => CartItemResource::collection($this->whenLoaded('items')),
            'coupon'      => $this->whenLoaded('coupon', fn () => ['code' => $this->coupon->code]),
            'subtotal'    => ['rial' => $this->subtotal, 'toman' => Money::rial($this->subtotal)->formatToman()],
            'discount'    => ['rial' => $this->discount_total, 'toman' => Money::rial($this->discount_total)->formatToman()],
            'total'       => ['rial' => $this->total, 'toman' => Money::rial($this->total)->formatToman()],
            'updated_at'  => $this->updated_at->toIso8601String(),
        ];
    }
}
```
Domain exceptions (e.g. `CouponNotApplicable`) are rendered centrally (exception handler) as `422` JSON for API/AJAX and as a validation-style error for Livewire.

### C5.6 Example: Livewire Form object → same DTO and Action
```php
final class ApplyCouponForm extends Form
{
    public string $code = '';

    public function rules(): array
    {
        return ApplyCouponRequest::baseRules();
    }

    public function toDto(int $cartId, int $customerId): ApplyCouponData
    {
        $this->code = mb_strtoupper(PersianNormalizer::digits(trim($this->code)));
        $this->validate();

        return new ApplyCouponData(cartId: $cartId, code: $this->code, customerId: $customerId);
    }
}

// in the Livewire component
public function apply(ApplyCoupon $applyCoupon): void
{
    $cart = $applyCoupon($this->form->toDto($this->cart->id, auth()->id()));
    $this->dispatch('cart-updated');
}
```

### C5.7 Example: Filament resource page → DTO and Action
```php
final class CreateBrand extends CreateRecord
{
    protected static string $resource = BrandResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateBrandAction::class)(BrandData::fromArray($data));
    }
}
```
Filament form validation rules come from the same source as the Form Request (`StoreBrandRequest::baseRules()`), applied to the Filament fields so admin and HTTP validation never drift apart.

### C5.8 Where each piece is required

| Entry point | Validation | DTO | Action | Response |
|---|---|---|---|---|
| Controller (webhooks, payment callback, AJAX, P2 API) | **Form Request** | `$request->toDto()` | yes | **API Resource** (JSON) or redirect |
| Livewire component | Livewire Form object reusing Form Request `baseRules()` | `$form->toDto()` | yes | view / events |
| Filament resource/page/action | Filament field rules from the same `baseRules()` | `XData::fromArray($data)` | yes | Filament notification |
| Jobs / console commands | input checked in the command/job | `XData::fromArray()` | yes | logs |


---

# Part D: Data model, flows, routes, integrations, images, security, testing, DevOps

> Sections D0–D11 are the complete MVP technical specification (entities, locations, shipping, Tipax, Zarinpal, images, jobs, open questions, integration setup guide). Sections D12–D22 add schema conventions, flows, routes, components, image device matrix, security, performance, testing, DevOps, the search engine plan and reporting.

## D0. Rules for AI agents working on this project

1. **Money is stored as integer Rial** in every table and API call. Show Toman (Rial ÷ 10) only in the UI.
2. **Weights in grams, lengths in millimetres** in our database. Convert to the units each vendor wants at the API boundary (Tipax wants kg and cm).
3. **Never call Tipax or Zarinpal from a controller directly.** Use the `ShippingCarrier` and `PaymentGateway` interfaces (sections D6.8 and D7.6).
4. **Every external call is logged** (request, response, duration, status) in `integration_logs`. Never log API keys, tokens, passwords or full card numbers.
5. **Snapshot data on orders:** an order stores copies of product names, prices, addresses and shipping quotes, never only foreign keys.
6. **Idempotency:** payment verification, order placement and shipment creation must be safe to run twice.
7. **Persian first:** all user-facing text is Persian, RTL, with Shamsi (Jalali) dates in the UI. Store timestamps in UTC.
8. **Request → DTO → Action → Resource** (C5) for every write and every JSON response: Form Requests validate, `toDto()` builds a readonly DTO, the controller/Livewire/Filament passes the DTO to an Action, JSON is returned through an API Resource.
9. **Prescription medicines are out of MVP scope.** Products with `requires_prescription = true` (always true for `product_type = rx`) cannot be activated for sale or added to the cart until phase 2. **OTC medicines are sellable in the MVP** (owner's decision, 2026-11-07, S10); the unsellable types are `config('catalog.unsellable_product_types')` (`['rx']`), so OTC can be switched off again with one line. The legal check is still open (D10 #8).

---

## D1. Business model in one paragraph

We sell cosmetics, personal-care products, supplements and (later, via a licensed partner pharmacy) OTC medicines. We do not keep stock. We buy each order from a provider (main provider: Sina's distribution company) at a discount, receive it, pack it and ship it with **Tipax** (other cities) or our own courier (our city). Customers pay online through **Zarinpal**. Profit per order = selling price − provider cost − shipping cost − payment fee − packaging.

---

## D2. Modules

| Module | Responsibility |
|---|---|
| Catalog | categories, brands, products, variants, attributes, tags, images, bundles |
| Pricing & Providers | providers, provider prices, commission, price records, pricing engine, price imports |
| Customers | accounts, OTP login, addresses, wishlist, reviews, questions, stock alerts |
| Cart & Checkout | cart, coupons, shipping quotes, payment start |
| Orders | orders, items (with price snapshots), order and order item status logs, provider purchases, returns |
| Shipping | carriers, services, boxes, quotes, shipments, packages, zones, couriers, carrier city mapping |
| Payments | payments, payment attempts, refunds, reconciliation |
| Locations | provinces, counties, cities, regions, neighbourhoods |
| Content | banners, home sections, flash deals, static pages, FAQ, menus, contact messages |
| Settings & Trust | settings, social links, trust badges, pharmacist in charge, SEO redirects |
| Search | search index, search logs, synonyms |
| Admin & System | admin users, roles, notification templates/logs, activity log, integration log |

---

## D3. Entities (MVP)

Conventions: every table has `id` (bigint), `created_at`, `updated_at`. `slug` columns are unique. Persian text fields are `*_fa`; English names `*_en` where useful. Soft deletes on catalog and content tables.

### D3.1 Catalog

**categories**: `parent_id` (nullable), `name_fa`, `name_en`, `slug`, `description`, `sort`, `is_active`, `seo_title`, `seo_description`. Images are medialibrary collections (decided 2026-11-07 in S09): `icon` (preset `category_icon`) and the category-page banner as `banner_desktop` + `banner_mobile` (presets `category_banner_desktop`/`category_banner_mobile`).

**brands**: `name_fa`, `name_en`, `slug`, `country`, `description`, `commission_percent` (nullable, S15; D3.2), `is_active`, `seo_title`, `seo_description`. The logo is the medialibrary collection `logo` (preset `brand_logo`), not an `*_image_id` column (decided 2026-11-07 in S09).

**products**

| Field | Type | Note |
|---|---|---|
| name_fa, name_en | string | |
| slug | string | unique |
| brand_id | FK | |
| primary_category_id | FK | plus pivot `category_product` for extra categories |
| default_variant_id | FK nullable | the admin's default SKU (decided 2026-11-07, S12); see «Default SKU» below |
| product_type | enum | `cosmetic`, `personal_care`, `supplement`, `otc`, `rx`, `medical_device` |
| requires_prescription | bool | blocks add-to-cart in MVP |
| irc_code | string | IFDA registration code, shown on PDP |
| short_description, description | text | |
| ingredients, usage, warnings, side_effects, interactions | text | PDP tabs |
| status | enum | `draft`, `active`, `hidden`, `discontinued` |
| seo_title, seo_description | string | |

**product_variants** (the sellable unit)

| Field | Type | Note |
|---|---|---|
| product_id | FK | |
| sku, barcode | string | |
| title_fa | string | e.g. "۳۰ عددی", "طعم پرتقال" |
| sort | int | admin's order (drag and drop); decides the next SKU when the default is out of stock |
| official_price | bigint Rial nullable | «قیمت مصرف‌کننده (روی جعبه)», the compare-at price: caps price and sale price for every product type; required for medicines. From S15 it lives on the price record (D3.2) |
| price | bigint Rial | «قیمت فروش»: our selling price. From S15 it is calculated only (supplier price + commission, D3.2) and lives on the price record; the admin cannot type it |
| sale_price | bigint Rial nullable | «قیمت حراج»: typed by the admin, below price. From S15 it lives on the price record |
| current_price_id | FK nullable | the latest `variant_prices` record (S15) |
| stock_status | enum | `available`, `on_request`, `out_of_stock` (no own stock; reflects provider availability) |
| expiry_date | date nullable | shown on PDP |
| is_near_expiry | bool | feeds "زودمصرف" section |
| is_damaged_box | bool | feeds "کج‌جعبه" section |
| weight_g | int | packaged weight |
| length_mm, width_mm, height_mm | int | packaged dimensions |
| dimensions_verified | bool | true after someone measured it |
| ship_separately | bool | bulky items ship in their own box |
| is_fragile, is_liquid | bool | maps to Tipax content "مایعات-شکستنی" |
| requires_cold_chain | bool | blocked from shipping in MVP |
| max_per_order | int nullable | |
| image | medialibrary `image` (optional) | the SKU's own photo (another pack or flavour; preset `product`); falls back to the product's main photo in the admin table, cart and the S13 variant picker (decided 2026-11-07) |
| is_active | bool | |

**At least one SKU** (decided 2026-11-07, S13b): the product create form asks for the first SKU (title, SKU code, stock, box/selling/sale price) and `CreateProduct` saves both in one transaction and makes it the default; deleting a product's last SKU is refused, even for a draft.

**Default SKU** (decided 2026-11-07, S12): `Product::getDefaultVariant()` is the SKU shown on cards, in quick view and in the search box, sent to Meilisearch, and added by «افزودن به سبد» (S19): the admin's choice (`default_variant_id`) while it is active and in stock; otherwise the first active, in-stock variant in `sort` order; when nothing is in stock, the admin's choice or else the first active variant (shown «ناموجود»). Only the choice is stored, so the default returns to it by itself once it is back in stock. Admin: «پیش‌فرض کن» in the variants table, drag to reorder (`SetDefaultVariant`, `ReorderProductVariants`). The struck-through price is the box price whenever the customer pays less (`compareAtPrice()`; without a box price, the price before the sale), and discount % is measured against it. The variant form shows a «قیمت‌ها چطور کار می‌کنند» guide above the price fields; S15 adds the engine paragraphs to it.

**collections** (decided 2026-11-07): `name_fa`, `name_en`, `slug`, `description`, optional icon (preset `category_icon`, shown at 24 px), `is_system` (not deletable), `is_active`, `starts_at`, `ends_at`, `sort`. Pivot **collection_product** (`collection_id`, `product_id`, `sort`): hand-picked products in the admin's order. Page `/collections/{slug}` uses the PLP (Meilisearch filter `collection_ids`; changing a collection refreshes its products' documents). The system collection `popular` («محصولات محبوب», created by the migration, slug fixed) feeds the empty search box and can be a home widget. The collection page's default sort is «پیشنهادی» (the admin's order): Meilisearch has no per-collection position, so the listing takes every matching id (≤ 2000), orders them by `collection_product.sort` and pages them itself; the database fallback orders the same way. Admins pick products by SKU (one option per SKU with image, name, SKU title · code · #id); the product is what gets saved.

**Admin tables** show a round thumbnail first (products, brands, categories, tags, SKUs; `Media\Filament\Tables\ImageThumbnail`, decided 2026-11-07).

**attributes**: `name_fa`, `code`, `type` (`select`, `multi_select`), `is_filterable`, `sort`. **attribute_values**: `attribute_id`, `value_fa`, `sort`. Pivot **product_attribute_value**. Used for: form (tablet, syrup, cream…), "suitable for" (age, gender), skin type, hair type. Attributes are chosen from lists only; the `text` type was dropped (decided 2026-11-07 in S11: free details belong in the description). `StandardAttributesSeeder` adds the standard attributes (form, suitable_for, age_group, benefit, skin_type, hair_type) based on the reference sites' filters (darunet «نوع دارو»/«مناسب برای», mosbatesabz «فرم محصول»/«رده سنی»/«فیلتر کارایی»); it only adds what is missing, and admins add or edit attributes and values in the admin.

**tags**: `name_fa`, `name_en` (optional, for the slug; decided 2026-11-07 in S11), `slug`, `type` (`nutrient`, `need`, `general`), `is_featured`, `sort`. The icon is the medialibrary collection `icon` (preset `category_icon`). Pivot **product_tag**. Powers home chips ("آهن", "ویتامین D", "بی‌خوابی"); featured tags in `sort` order.

**images**: see sections D8 and D14. Product photos are the medialibrary collection `gallery` (preset `product`, several images in order, the first is the main photo; decided 2026-11-07 in S10) instead of a `product_images` pivot. Persian alt text is the media custom property `alt_fa`; linking a photo to one variant is left for the PDP variant picker (S13).

**product_faqs**: `product_id`, `question`, `answer`, `sort`.

**product_relations**: `product_id`, `related_product_id`, `type` (`related`, `alternative`, `frequently_bought`).

**bundles**: `name_fa`, `slug`, `image_id`, `price`, `is_active`; **bundle_items**: `bundle_id`, `variant_id`, `quantity`.

### D3.2 Pricing and providers

> **Pricing model (owner, 2026-11-09; replaces the S12 «commission per product only», the store default % and «قیمت دستی»).** The selling price is never typed: it is the cheapest available supplier price plus the commission, capped by the printed (box) price. Every change is kept as a price record.

**providers** («تأمین‌کنندگان»): `name`, `contact_name`, `phone`, `address`, `city_id`, `payment_terms`, `is_active`, `notes`.

**provider_prices**: `provider_id`, `variant_id`, `cost` (Rial, what we pay), `is_available` («موجود نزد تأمین‌کننده»: set by the supplier or the Excel import), `is_enabled` («فروش از این تأمین‌کننده»: the admin's own on/off, owner 2026-11-09), `lead_time_hours`, `updated_at`. Unique (`provider_id`, `variant_id`). **provider_price_changes** keeps every change of a row (cost, both switches, lead time, who, when). Suppliers and their prices are shown **only on the SKU page** (one page per SKU, opened from the product's SKU table) to admins with supplier permission (super admin by default); each row shows its cost and the selling price it would give with the commission. **Active supplier** = the cheapest row that is enabled, available and whose provider is active; stopping it hands over to the next cheapest automatically (site and Meilisearch follow). The product's SKU table shows the active supplier («تأمین‌کننده فعال») to the same admins. An inactive provider's rows are ignored.

**Commission** (owner, 2026-11-14, S15b): five levels; for each supplier of a SKU the first that is set applies: (1) **SKU × supplier** (`provider_prices.commission_percent`), (2) **product × supplier** (`product_providers.commission_percent`), (3) **product** (`products.commission_percent`), (4) **brand × supplier** (`brand_providers.commission_percent`), (5) **brand** (`brands.commission_percent`). A SKU cannot be saved when neither its product nor its brand has a general commission; the supplier levels only override it.

**Which supplier the website sells from** (owner, 2026-11-14, S15b): the SKU's preferred supplier («تأمین‌کننده انتخابی», `provider_prices.is_preferred`, one per SKU) while it can sell; else the product's preferred supplier (`product_providers.is_preferred`, one per product) while it can sell for that SKU; else the supplier whose **selling price is lowest** (each cost with its own commission; ties go to the lower cost); else the printed price. Price records keep `provider_choice` (`preferred_sku`, `preferred_product`, `cheapest`) and the commission level in `commission_source`; order items snapshot both (D3.4). A **«راهنمای قیمت‌گذاری»** page in the admin (قیمت‌گذاری menu, every admin) teaches the whole model with definitions, examples, where each thing is set and common questions.

**Pricing engine** (`Pricing\Actions\CalculateVariantPrice`):
```
supplier price = lowest cost among available provider_prices of the SKU
commission %   = product.commission_percent ?? brand.commission_percent
selling price  = round_up(supplier price × (1 + commission %/100), 1,000 toman = 10,000 Rial)
                 never above the printed (box) price; when capped, the SKU shows «سود کمتر از کمیسیون»
haraj          = typed by the admin, optional, must be below the selling price
```
Example: printed 1,000,000 toman, cheapest supplier 850,000, commission 10 % → selling 935,000 toman; with a haraj of 899,000 the customer pays 899,000 and the margin is 49,000. **No supplier, or no active supplier → the SKU is sold at its printed price** and stays in stock (owner, 2026-11-09; flag `sold_at_printed`); only a SKU with neither an active supplier nor a printed price is «ناموجود» (its last price is kept). A new SKU starts out of stock until the job has priced it. A haraj that is no longer below a new selling price is removed, noted on the record, and flagged.

**Storage** (decided 2026-11-09, S14/S15): the records below are the source of truth; `product_variants.price` is a copy of the latest record's selling price written only by the pricing job in the same transaction (search, cards and listings keep reading one row). `official_price` (printed) and `sale_price` (haraj) are the admin's inputs on the SKU and are copied into each record. A new record is written only when something in it changes. **Stock is automatic** (the admin no longer chooses it): in stock when sold from an active supplier or at the printed price, out of stock otherwise. SKUs without any supplier row are listed as «بدون تأمین‌کننده» (they sell at the printed price, so the commission does not shape their margin). `php artisan pricing:recalculate` queues every SKU once after the deploy and runs nightly at 03:00 Tehran time; only real changes are recorded. The SKU page shows the price records as a full table (time, reason, supplier, cost, commission and source, printed, selling, haraj, margin per unit, notes, who), refreshed every 10 s. **Guides** (owner, 2026-11-09): the SKU page, the SKU form, and the commission fields on the product and brand forms show a short Persian guide with a worked example of how pricing works.

**variant_prices** (price records, never updated): `variant_id`, `official_price` (printed), `provider_id` + `provider_cost` (the supplier price used), `commission_percent`, `commission_source` (`product`, `brand`), `price` (selling), `sale_price` (haraj), `reason` (`printed_changed`, `haraj_changed`, `commission_changed`, `provider_price_changed`, `import`, `initial`), `changed_by` (admin id, nullable for jobs), `flags` (json: `capped_by_printed`, `haraj_removed`, `change_over_15_percent`), `created_at`. Any change of the printed price, the haraj, the commission (product or brand) or a supplier price creates a new record. `product_variants.current_price_id` points to the latest record and the site always reads prices from it. **Order items keep `variant_price_id`** (S25/S29), so the sold price, printed price, haraj, commission and supplier cost of that moment stay with the order for real-margin reports. Provider costs never reach Meilisearch or any storefront view.

**Background work** (owner, 2026-11-09): every price calculation runs in a job on the **`pricing`** queue (`RecalculateVariantPrice`, `RecalculatePrices` for a brand, a product or everything); Excel imports run on their own **`price-import`** queue. The admin form shows the expected selling price live while typing; the saved record is written by the job.

**price_imports** (as built in S16): `file_path`, `original_name`, `status` (`pending`, `processing`, `previewed`, `applying`, `done`, `failed`), `rows_total`, `rows_matched`, `rows_updated`, `rows_failed`, `error` (file could not be read), `admin_id`, `applied_at`; each row names its own supplier, so there is no `provider_id`. **price_import_rows**: the checked rows for the preview (supplier, matched SKU and how, new and old cost and switches, lead time, the selling price the new cost gives with the commission, whether it changes anything, or the reason it cannot be imported, and the raw cells). The error report is generated from these rows when downloaded.

### D3.3 Customers

**customers**: `mobile` (unique, `09xxxxxxxxx`), `first_name`, `last_name`, `national_id` (nullable), `email` (nullable), `birth_date`, `gender`, `is_blocked`, `last_login_at`.

**otp_codes**: `mobile`, `code_hash`, `purpose` (`login`), `expires_at` (2 min), `attempts`, `ip`. Rate limit: 1 per 2 min per mobile, 5 per hour per IP.

**addresses**: `customer_id`, `title`, `receiver_name`, `receiver_mobile`, `province_id`, `city_id`, `region_id` (nullable), `neighbourhood_id` (nullable), `full_address`, `plaque`, `unit`, `floor`, `postal_code` (10 digits), `latitude`, `longitude`, `is_default`.

**wishlists**: `customer_id`, `product_id`.
**reviews**: `product_id`, `customer_id`, `order_item_id` (nullable → verified buyer), `rating` 1–5, `body`, `status` (`pending`, `approved`, `rejected`).
**product_questions**: `product_id`, `customer_id`, `question`, `answer`, `answered_by_admin_id`, `status`.
**stock_alerts**: `customer_id`, `variant_id`, `notified_at`.

### D3.4 Cart, checkout and orders

**carts**: `customer_id` (nullable), `session_token` (guest), `coupon_id`, `address_id`, `shipping_quote_id`, `expires_at`.
**cart_items**: `cart_id`, `variant_id`, `quantity`, `unit_price_snapshot`.

**coupons**: `code` (unique, case-insensitive), `type` (`percent`, `fixed`, `free_shipping`), `value`, `max_discount`, `min_order_amount`, `starts_at`, `ends_at`, `usage_limit_total`, `usage_limit_per_customer`, `first_order_only`, `is_active`. Pivots **coupon_categories**, **coupon_brands**, **coupon_products** (include lists).
**coupon_usages**: `coupon_id`, `customer_id`, `order_id`, `discount_amount`.

**orders**

| Field | Note |
|---|---|
| number | human-readable, e.g. `PH-140508-000123` |
| customer_id | |
| status | see D3.5 |
| payment_status | `unpaid`, `pending`, `paid`, `refunded`, `partially_refunded` |
| address_snapshot | JSON copy of the address incl. city names and Tipax city id |
| subtotal, discount_total, shipping_total, total | bigint Rial |
| coupon_id, coupon_code | |
| shipping_method | `tipax`, `own_courier` |
| carrier_service_id | FK to `carrier_services` |
| shipping_quote_snapshot | JSON of the chosen quote |
| shipping_cost_quoted, shipping_cost_actual | Rial; actual comes from Tipax after pickup |
| delivery_slot_id | own courier only |
| customer_note, admin_note | |
| placed_at, paid_at, cancelled_at, delivered_at | |

**order_items**: `order_id`, `variant_id`, `variant_price_id` (the price record it was sold at, D3.2), `name_snapshot`, `sku`, `quantity`, `unit_price`, `discount`, `total`, `provider_id`, `unit_cost` (for profit), `status` (see below).

**Price snapshot on every order item** (owner, 2026-11-14): the commission lives on products and brands and supplier costs change, so when the customer places the order (`PlaceOrder`, S25) each item copies the values of that moment onto itself, not only the `variant_price_id`: `provider_id` and `unit_cost` (the active supplier and its cost, null when sold at the printed price), `commission_percent` and `commission_source` (one of the five levels), `provider_choice` (preferred or cheapest), `official_price` (printed), `list_price` (selling price before haraj), `sale_price` (haraj), `unit_price` (what the customer pays). Gross and net margin reports (S41, S42) read these columns, so later changes to a commission, a supplier cost or a price record never change a past order.

**order_status_logs** (owner, 2026-11-14; was `order_status_histories`): `order_id`, `from_status`, `to_status`, `actor_type` (`system`, `admin`, `customer`, `carrier`), `actor_id`, `note`, `created_at`. One row for every change of an order's status; never updated.

**order_item_status_logs** (owner, 2026-11-14): `order_item_id`, `from_status`, `to_status`, `actor_type`, `actor_id`, `note`, `created_at`. Each item has its own status, because items are bought, received, packed, cancelled or returned one by one (`pending`, `sourcing`, `purchased`, `received`, `packed`, `shipped`, `delivered`, `cancelled`, `returned`); every change writes a row, and the order admin (S29) shows both logs as timelines.

**provider_purchases**: `provider_id`, `status` (`requested`, `confirmed`, `received`, `partially_received`, `cancelled`), `total_cost`, `requested_at`, `received_at`; **provider_purchase_items**: `provider_purchase_id`, `order_item_id`, `variant_id`, `quantity`, `unit_cost`, `received_quantity`.

**return_requests**: `order_id`, `status` (`requested`, `approved`, `rejected`, `received`, `refunded`), `reason`, `refund_amount`; **return_request_items**.

### D3.5 Order state machine

```
pending_payment ──(Zarinpal verify OK)──▶ paid
paid ──▶ sourcing            (provider purchase requested)
sourcing ──▶ ready_to_pack   (all items received)
ready_to_pack ──▶ packed
packed ──▶ awaiting_pickup   (Tipax order registered) │ out_for_delivery (own courier)
awaiting_pickup ──▶ in_transit (Tipax picked up)
in_transit ──▶ delivered
any before packed ──▶ cancelled (refund if paid)
delivered ──▶ returned (via return_requests)
pending_payment ──(30 min timeout / NOK)──▶ payment_failed
```
Every transition writes `order_status_logs` (and each item's change writes `order_item_status_logs`) and may send an SMS (see `notification_templates`).

### D3.6 Content, settings, search, admin

- **Home widgets** (decided 2026-11-07; replaces `home_sections` and banner slots): **home_widgets**: `type` (`slider`, `collection`, `brands`, `categories`, `tags`, `trust`; S33 adds `flash_deal`, `bundles`), `title_fa`, `settings` (json: e.g. `collection_id`, ordered `brand_ids` / `category_ids`), `sort`, `is_active`, `starts_at`, `ends_at`. The admin adds, sets up, schedules and drags widgets into order on «چیدمان صفحه اصلی», with a preview link. **Every widget shows at most 6 items** (6 banners, 6 cards, 6 logos, 6 icons, 6 chips); a collection rail adds «مشاهده همه». ≤ 8 live widgets (the page warns). **A widget is shown only with at least one item** (owner, 2026-11-07): a slider needs a visible banner, a collection rail an in-stock product of a visible collection, brands / categories / tags an active item; the trust block is always shown (`Content\Queries\HomeWidgetItems`). The «در صفحه» column of «چیدمان صفحه اصلی» says why a widget is hidden; with no widget to show, the home page shows a short welcome. **Sliders** (owner, 2026-11-09): the banner slider swipes with an arrow on each side and no dots; the other rows become sliders with side arrows only when their items do not fit on the screen (Swiper, C2). **No rendered-HTML cache** for the home page (decided 2026-11-07, S13b): widget queries are light and cards must show live prices.
- **banners** (decided 2026-11-07): a library («بنرها») of banners created once and reused: `title`, `link_type` (`category`, `collection`, `brand`, `tag`, `product`, `categories_list`, `collections_list`, `brands_list`, `external`), `link_id` (for an item), `external_url` (for `external`; opens in a new tab with `rel="noopener"`), `is_active`, `starts_at`, `ends_at`; images: medialibrary collections `desktop` (1920×600), `tablet` (1536×768), `mobile` (800×1000) (D14.2). The admin picks the target item rather than typing its URL, so links follow slug changes and a banner whose target is gone or inactive is hidden.
- **banner_placements** (as built in S13b): `banner_id`, `place` (`home_widget`, `category`, `collection`, `brand`, `tag`, `categories_list`, `collections_list`, `brands_list`), `place_id` (the widget, category, collection, brand or tag id; null for the list pages), `sort`. A place shows one banner, or a slider when it has several; **at most 6 per place**; a banner whose target is hidden or gone is not shown. The admin sets banners only in home slider widgets: the «جایگاه بنرها» page for the page-top places was removed at the owner's request (2026-11-07), so the storefront still renders page-top banners but nothing sets them for now. The S07 category banner images stay as the category page's banner until the owner decides to retire them (that means rewriting the S09 image tests).
- **flash_deals**: `variant_id`, `deal_price`, `starts_at`, `ends_at`, `quantity_limit`, `sold_count`.
- **pages**: `slug` (`about`, `terms`, `privacy`, `returns`, `shipping`, `payment-methods`, `buying-guide`, `licenses`), `title`, `body`, `seo_*`.
- **faqs**: `group`, `question`, `answer`, `sort`.
- **menus** / **menu_items**: `location` (`header`, `footer_1`, `footer_2`, `mobile`), `title`, `url`, `parent_id`, `sort`.
- **contact_messages**: `name`, `mobile`, `email`, `subject`, `body`, `status`.
- **settings**: key/value (`site_name`, `logo_image_id`, `phone`, `email`, `address`, `working_hours`, `free_shipping_threshold`, `min_order_amount`, `maintenance_mode`, `origin_address_id`, …).
- **social_links**: `platform`, `url`, `sort`.
- **trust_badges**: `title`, `image_id`, `link_url`, `embed_code` (e-Namad), `sort`.
- **pharmacists**: `full_name`, `license_number`, `photo_image_id`, `bio`, `is_responsible`.
- **seo_redirects**: `from_path`, `to_path`, `status_code`.
- **search_logs**: `query`, `normalized_query`, `results_count`, `customer_id`, `clicked_product_id`.
- **search_synonyms**: `term`, `synonyms` (JSON), e.g. `ویتامین دی` ↔ `D3`, `ویتامین D`.
- **admin_users**, **roles**, **permissions** (use `spatie/laravel-permission`). Roles: `super_admin`, `operator`, `content_editor`, `pharmacist` (phase 2).
- **notification_templates**: `event`, `channel` (`sms`), `body` with placeholders; **notification_logs**.
- **activity_logs**: `admin_id`, `subject_type`, `subject_id`, `action`, `changes` JSON.
- **integration_logs**: `service` (`tipax`, `zarinpal`, `sms`), `operation`, `request` (redacted), `response`, `http_status`, `duration_ms`, `correlation_id`, `order_id`.

---

## D4. Locations

### D4.1 Source data
- Master list: **sajaddp/list-of-cities-in-Iran** (31 provinces, 484 counties, 1,672 cities; official divisions to end of 1404; JSON/CSV/XLSX). License GPL-3.0, coordinates CC BY 4.0. **[CONFIRM]** licence fit with a lawyer before shipping a closed-source product.
- Tehran regions and neighbourhoods (22 regions, 568 neighbourhoods): **zarbinco/laravel-iran-locations** (Laravel 11–13, MIT code, beta) or our own polygons.

### D4.2 Tables
- **provinces**: `name_fa`, `slug`, `source_id`.
- **counties**: `province_id`, `name_fa`, `source_id`.
- **cities**: `province_id`, `county_id`, `name_fa`, `type` (`city`, `village`), `latitude`, `longitude`, `is_delivery_enabled`, `source_id`.
- **regions**: `city_id`, `name_fa`, `number`.
- **neighbourhoods**: `region_id`, `city_id`, `name_fa`, `polygon` (geometry, nullable).
- **carrier_city_mappings**: `carrier_id`, `city_id`, `carrier_city_id` (int), `carrier_city_name`, `carrier_province_name`, `has_branch` (bool), `last_synced_at`. Unique (`carrier_id`, `carrier_city_id`).

### D4.3 Tipax city sync (artisan command `tipax:sync-cities`, weekly)
1. `GET /api/OM/v4/Cities/plusstate` → all Tipax cities with province names.
2. `GET /api/OM/v4/Cities/BranchCities` → cities with an active Tipax branch (`has_branch = true`).
3. Match to our `cities` by normalized Persian name + province (normalize ی/ي, ک/ك, ZWNJ, spaces). Unmatched rows go to an admin review screen.
4. Checkout only offers Tipax for cities with a mapping.

---

## D5. Pricing of shipping: concepts

- Tipax receives **weight (kg), length, width, height (cm) and declared value (Rial)** per package and returns the price; Tipax applies its own volumetric rules. **[CONFIRM]** the volumetric divisor with Tipax account manager; we keep our own estimate only for the fallback table.
- Our estimate (fallback and sanity check): `volumetric_kg = L×W×H(cm) / divisor` (default divisor 5000, stored on `carriers`), `chargeable = max(actual_kg, volumetric_kg)`.
- Packing: before quoting, the cart is packed into **shipping_boxes** (first-fit-decreasing by volume, respecting box max weight; `ship_separately` items get their own box). Each box becomes one Tipax `packageInput`.

### D5.1 Shipping tables
- **carriers**: `code` (`tipax`, `own_courier`), `name`, `driver`, `base_url`, `credentials` (encrypted JSON), `volumetric_divisor`, `is_active`.
- **carrier_services**: `carrier_id`, `external_id` (Tipax serviceId), `code`, `name_fa`, `delivery_days`, `supports_cod`, `is_active`, `sort`.
- **shipping_boxes**: `name`, `inner_length_mm`, `inner_width_mm`, `inner_height_mm`, `max_weight_g`, `empty_weight_g`, `tipax_packing_id`, `tipax_pack_type`, `cost`, `is_active`.
- **origins**: `name`, `address`, `city_id`, `postal_code`, `latitude`, `longitude`, `tipax_address_id` (originId), `is_default`.
- **shipping_quotes**: `cart_id`, `order_id`, `carrier_id`, `carrier_service_id`, `origin_id`, `destination_city_id`, `packages` JSON (box, dims, weight, value, items), `price` (Rial), `price_breakdown` JSON, `delivery_days`, `expires_at` (now + 20 min), `raw_response`.
- **shipments**: `order_id`, `carrier_id`, `carrier_service_id`, `status`, `correlation_id`, `cost_quoted`, `cost_actual`, `cod_amount`, `registered_at`, `picked_up_at`, `delivered_at`, `cancelled_at`, `last_event`, `last_synced_at`.
- **shipment_packages**: `shipment_id`, `box_id`, `weight_g`, `length_mm`, `width_mm`, `height_mm`, `declared_value`, `tracking_code`, `custom_title`, `label_url`.
- **shipment_events**: `shipment_id`, `tracking_code`, `status_code`, `status_title`, `event_title`, `occurred_at`, `raw`.
- **delivery_zones** (own courier): `name`, `city_id`, `neighbourhood_ids` JSON or `polygon`, `price`, `free_threshold`, `eta_hours`, `is_active`.
- **delivery_slots**: `zone_id`, `weekday`, `starts_at`, `ends_at`, `capacity`.
- **couriers**: `name`, `mobile`, `is_active`.
- **shipping_rules**: `type` (`free_over_amount`, `fixed_markup`, `percent_markup`, `max_shown_price`, `round_up`), `value`, `carrier_id` (nullable), `is_active`.

---

## D6. Tipax integration

### D6.1 Environments and access

| | URL |
|---|---|
| Documentation hub | https://document.tipaxapi.org |
| Test panel (create test account) | https://omtest.tipax.ir/register then /login |
| Test API base | https://omtestapi.tipax.ir |
| Swagger V4 (test) | https://omtestapi.tipax.ir/swagger/index.html?urls.primaryName=V4+Docs |
| Swagger JSON V4 / V3 | https://omtestapi.tipax.ir/swagger/v4/swagger.json, /swagger/v3/swagger.json |
| Production panel | https://etipaxco.com (eTipax) |
| Production API base | **[CONFIRM]** with Tipax (not listed on the docs hub) |
| Support | 021-41036000 ext. 4853, 4807, 4810, 4813, 4812 |

Test orders are never finalized. Production requires a production eTipax account; Tipax customers have a `productionApiAccess` flag and contracts. **[CONFIRM]** contract terms, credit/wallet, pickup schedule and COD settlement with the Tipax account manager.

API V4 (latest): 76 endpoints in 17 groups. V3 (stable): 107 endpoints, includes **Webhook** management that V4 does not list. We use V4 for business calls and V3 for login and webhooks.

### D6.2 Authentication
1. In the test panel: register a username/password, log in, confirm account details, then **Profile → Business profile** to get the **API key**.
2. Get a token:
   `POST /api/OM/v3/Account/token` body `{ "username": "...", "password": "...", "apiKey": "..." }` → `{ accessToken, refreshToken, expiresIn }`.
   (Alternative: `POST /api/OM/v3/Account/tokenwithphonenumber` with `phoneNumber`.)
3. Refresh before expiry: `POST /api/OM/v4/Account/RefreshToken` body `{ "token": "<access>", "refreshToken": "<refresh>" }` → `data.access_token`, `data.refresh_token`, `data.expires_in`.
4. Send `Authorization: Bearer <accessToken>` on every call.
5. Cache the token in Redis (`tipax:token`) for `expiresIn − 60s`; one refresh at a time (Redis lock).

Responses use an envelope: `{ data, isSuccess, statusCode, message, totalItemCount, pageNumber, pageSize, pageCount }`. `statusCode` is a string enum (`Success`, `BadRequest`, `UnAuthorized`, `CityNotFound`, `ServiceNotAvailable`, `ParcelIsNotCancalable`, …). Treat `isSuccess = false` as an error even with HTTP 200.

### D6.3 Endpoints we use

| Purpose | Method & path |
|---|---|
| Provinces | `GET /api/OM/v4/States` |
| Cities (+province) | `GET /api/OM/v4/Cities/plusstate` |
| Cities with branch | `GET /api/OM/v4/Cities/BranchCities` |
| Services between two cities | `POST /api/OM/v4/Pricing/GetServicesBetweenCities` `{ sourceCityId, destinationCityId }` → `serviceList[{ serviceId, serviceKind, serviceKindId, deliveryDay }]` |
| All services | `POST /api/OM/v4/Pricing/GetServicesList` |
| Price quote | `POST /api/OM/v4/Pricing` (D6.4) |
| Price with saved origin address | `POST /api/OM/v4/Pricing/WithOriginAddressId` |
| Pack types / packings / contents | `GET /api/OM/v4/PackingType/GetPackTypes`, `/GetPackingTypes`, `/GetPackagingContents` |
| Payment types | `POST /api/OM/v4/PaymentType/Search` |
| Save our warehouse address | `POST /api/OM/v4/Addresses` (business address) |
| Register order (origin predefined) | `POST /api/OM/v4/OmOrder/WithPreDefinedOrigin` (D6.5) |
| Register return order | `POST /api/OM/v4/OmOrder/ReturnRegisterOrder` |
| Parcels of an order | `GET /api/OM/v4/Parcels/GetByOrderId/{orderId}` |
| Cancel parcel | `PUT /api/OM/v4/Parcels/Cancel/{trackingCode}` |
| Actual cost of parcel | `GET /api/OM/v4/Parcels/GetPriceDetailByTrackingCode/{trackingCode}` |
| Tracking (brief) | `GET /api/OM/v4/Tracking/BriefTracking/{trackingInput}` |
| Tracking (many) | `POST /api/OM/v4/Tracking/ByTrackingCode` |
| Tracking by our code | `POST /api/OM/v4/Tracking/{traceCode}` |
| Status & event lists | `GET /api/OM/v4/ParcelStatus`, `GET /api/OM/v4/ParcelStatus/GetEventWithStatus` |
| Wallet / credit | `GET /api/OM/v4/Customers/Wallet`, `/Customers/Credit` |
| Webhooks (V3) | `GET/POST/PUT /api/OM/v3/CustomerWebhook` `{ url, headers[{key,value}] }`; history `POST /api/OM/v3/CustomerWebhookHistory/GetAll` |
| Lockers (phase 2) | `POST /api/v4/Locker/SearchKiosks` |

### D6.4 Price quote request

`POST /api/OM/v4/Pricing`
```json
{
  "packageInputs": [
    {
      "origin": { "cityId": 1 },
      "destination": { "cityId": 85 },
      "weight": 1.2,
      "length": 25, "width": 20, "height": 10,
      "packageValue": 25000000,
      "packingId": 0,
      "packageContentId": 0,
      "packType": 0,
      "serviceId": 0,
      "paymentType": 10,
      "pickupType": 10,
      "distributionType": 10
    }
  ],
  "discountCode": null
}
```
Units (from Tipax's pricing tool): **weight kg, dimensions cm, packageValue Rial**.

Response: an array, one entry per package, with `originCity`, `destiationCity` (sic), `packNum` and one rate object per service family: `regularRate`, `regularPlusRate`, `expressRate`, `airExpressRate`, `sameDayExpressRate`. Each rate has `serviceId`, `serviceTitle`, `finalPrice`, `originalFinalPrice`, `beforeTaxAndDiscount`, `tax`, `discount`, `discountAmount`, `transportationCost`, `pickupPrice`, `deliveryPrice`, `packing`, `tracking`, `noticeOfDelivery`, `compensation`, `pdAmount`.
**[CONFIRM]** currency of `finalPrice` (Rial vs Toman). Note `GetSumTotalAmountByTrackingCodes` is documented as Toman.

How we use it: call once per checkout (cart hash + destination city), sum `finalPrice` of the chosen family across packages, apply `shipping_rules`, store in `shipping_quotes`, cache 20 min.

### D6.5 Order registration

`POST /api/OM/v4/OmOrder/WithPreDefinedOrigin` (origin = our saved warehouse address id)
```json
{
  "packages": [
    {
      "originId": 123456,
      "destination": {
        "cityId": 85,
        "fullAddress": "…",
        "no": "12", "unit": "3", "floor": "2",
        "postalCode": "1234567890",
        "latitude": "35.7", "longitude": "51.4",
        "beneficiary": { "fullName": "…", "mobile": "0912…", "phone": "", "nationalId": "" }
      },
      "weight": 1.2, "length": 25, "width": 20, "height": 10,
      "packageValue": 25000000,
      "packingId": 0, "packageContentId": 0, "packType": 0,
      "serviceId": 1,
      "paymentType": 10,
      "pickupType": 10,
      "distributionType": 10,
      "cod": 0,
      "enableLabelPrivacy": false,
      "customParcelTitle": "PH-140508-000123-1",
      "description": "",
      "pickupDate": null, "pickupStartTime": null, "pickupEndTime": null
    }
  ],
  "traceCode": "PH-140508-000123",
  "secondaryTraceCode": "<shipment id>",
  "successCallbackUrl": "https://api.example.ir/webhooks/tipax/order/success",
  "failureCallbackUrl": "https://api.example.ir/webhooks/tipax/order/failure"
}
```
- `successCallbackUrl` and `failureCallbackUrl` are **required**, valid http/https URLs. Registration is **asynchronous**: the response gives `correlationId` and `trackingCodesWithTitles[{ trackingCode, customTitle }]`; the final result arrives on the callback. **[CONFIRM]** callback payload format and whether it is signed.
- `traceCode` = our order number, so we can track by `POST /Tracking/{traceCode}`.

### D6.6 Codes (from Tipax docs)

**serviceId**

| id | Service |
|---|---|
| 1 | Ground one-day, normal express (ارسال زمینی یک روزه – اکسپرس عادی) |
| 2 | One-day (ارسال یک روزه) |
| 3 | Two-day (ارسال دو روزه) |
| 6 | International |
| 7 | Intra-city express (اکسپرس درون شهری) |
| 12 | Same day (سرویس همان روز) |
| 13 | Express plus (اکسپرس پلاس) |

Always ask `GetServicesBetweenCities` which of these are available for the route.

**paymentType**

| id | Meaning |
|---|---|
| 10 | Sender, prepaid at origin |
| 20 | Receiver, cash on delivery of the shipping fee |
| 30 | Receiver pays at delivery point |
| 40 | Sender, pay at origin from credit |
| 50 | Sender, from wallet |
| 60 | Sender, pay at origin from wallet |
| 80 | Sender, cash at origin |
| 90 | Sender, from branch credit |

MVP default: **50 (wallet)** or **40 (credit)** depending on the contract. **[CONFIRM]**.

**pickupType**: 10 = collect at customer (our warehouse), 20 = we drop at Tipax branch.
**distributionType**: 10 = deliver to receiver's address, 20 = receiver collects from branch.

**packType** (names from pricing tool): Eco pack, bulky & heavy, mini pack, package (بسته), envelope (پاکت).
**packingId**: Tipax cartons size 1–14 (3- or 5-layer), bubble-wrap equivalents, A3/A4/A5 envelopes, flyers, "no packing needed". Fetch ids from `GetPackingTypes` (returns min/max weight and dimensions per packing) and store them on `shipping_boxes.tipax_packing_id`.
**packageContentId**: use **"آرایشی، بهداشتی و داروهای مجاز"** (cosmetics, hygiene and permitted medicines) by default and **"مایعات-شکستنی"** (liquids, fragile) when any item `is_liquid` or `is_fragile`. Fetch ids from `GetPackagingContents`.

Parcel statuses (12) and events (18): load from `ParcelStatus` and `GetEventWithStatus` into a mapping table that converts Tipax status → our `orders.status`.

### D6.7 Tracking updates
1. Register our webhook URL once with `POST /api/OM/v3/CustomerWebhook` (add a secret header, e.g. `X-Webhook-Secret`).
2. Webhook endpoint verifies the header, stores raw payload in `shipment_events`, dispatches `ProcessTipaxEvent` job.
3. Fallback poller: scheduled job every 30 min calls `Tracking/ByTrackingCode` for shipments not delivered/cancelled and updated > 2 h ago.

### D6.8 Laravel design

```
app/Modules/Shipping/
  Contracts/ShippingCarrier.php      // quote(), createShipment(), cancel(), track(), syncCities()
  Data/PackageInput.php              // readonly DTO: weightG, lengthMm, widthMm, heightMm, valueRial, contentType
  Data/Quote.php, ShipmentResult.php, TrackingEvent.php
  Packing/BoxPacker.php              // first-fit-decreasing into shipping_boxes
  Carriers/Tipax/TipaxClient.php     // Http::baseUrl()->withToken()->retry(2, 300)->timeout(10)
  Carriers/Tipax/TipaxTokenStore.php // Redis cache + lock
  Carriers/Tipax/TipaxCarrier.php    // implements ShippingCarrier, unit conversion g→kg, mm→cm
  Carriers/Tipax/TipaxCodes.php      // enums: ServiceId, PaymentType, PickupType, DistributionType
  Carriers/OwnCourier/OwnCourierCarrier.php // zone-based price
  Fallback/RateTable.php             // used when Tipax is down
app/Modules/Shipping/Jobs/RegisterTipaxShipment.php   // idempotent on shipment id
app/Modules/Shipping/Jobs/ProcessTipaxEvent.php
app/Modules/Shipping/Console/TipaxSyncCities.php
app/Modules/Shipping/Routes/webhooks.php: POST /webhooks/tipax/order/{result}, POST /webhooks/tipax/events
```
- Timeouts: 10 s; checkout falls back to `RateTable` after one retry and flags the quote `is_estimate = true`.
- Config `config/shipping.php` + `.env`: `TIPAX_BASE_URL`, `TIPAX_USERNAME`, `TIPAX_PASSWORD`, `TIPAX_API_KEY`, `TIPAX_ORIGIN_ADDRESS_ID`, `TIPAX_WEBHOOK_SECRET`.

---

## D7. Zarinpal integration

### D7.1 Endpoints (API v4)

| Purpose | Production | Sandbox |
|---|---|---|
| Request payment | `POST https://payment.zarinpal.com/pg/v4/payment/request.json` | `https://sandbox.zarinpal.com/pg/v4/payment/request.json` |
| Redirect customer | `https://payment.zarinpal.com/pg/StartPay/{authority}` | `https://sandbox.zarinpal.com/pg/StartPay/{authority}` |
| Verify | `POST …/pg/v4/payment/verify.json` | sandbox equivalent |
| Inquiry (status only) | `POST …/pg/v4/payment/inquiry.json` | |
| Unverified list (last 100) | `POST …/pg/v4/payment/unVerified.json` | |
| Reverse (≤ 30 min, no fee) | `POST …/pg/v4/payment/reverse.json` | |

Headers: `Content-Type: application/json`, `Accept: application/json`. Sandbox: any UUID as `merchant_id`; sandbox authorities start with `S`.

### D7.2 Flow
1. **Request**: body `{ merchant_id, amount, currency: "IRR", description, callback_url, metadata: { mobile, email, order_id } }`. `amount` is Rial unless `currency` = `IRT`. Always send `IRR` explicitly. Success: `data.code = 100`, `data.authority`, `data.fee_type`, `data.fee`.
2. Save a `payments` row (`status = pending`, `authority`) and redirect to `StartPay/{authority}`.
3. **Callback**: Zarinpal redirects to `callback_url?Authority=…&Status=OK|NOK`. `NOK` = failed or cancelled; do not verify.
4. **Verify** (only on `OK`): body `{ merchant_id, amount, authority }`, amount must equal the original amount. `code 100` = verified now; `code 101` = already verified (treat as success, idempotent). Store `ref_id`, `card_pan` (masked), `card_hash`, `fee_type`, `fee`.
5. Mark order `paid`, record coupon usage, send SMS.
6. **Reconciliation job** every 10 min: call `unVerified.json` and verify any authority that belongs to our pending payments (customer closed the browser before the callback).
7. **Reverse** within 30 min for orders we cannot fulfil (requires the server IP to be set on the gateway; otherwise error −62). After 30 min, refunds go through Zarinpal's panel/refund service. **[CONFIRM]** refund API access.

### D7.3 Important errors

| Code | Meaning |
|---|---|
| −9 | Validation error (missing merchant, callback, description > 500 chars, amount out of range, bad referrer) |
| −10 | Invalid merchant_id or IP |
| −11 | Terminal not active |
| −12 | Too many attempts |
| −13 | Terminal limit reached |
| −14 | **Callback URL domain does not match the registered terminal domain** |
| −15/−16/−17/−19 | Terminal suspended / level too low / transactions banned |
| −41 | Max amount is 100,000,000 Toman |
| −50 | Verify amount differs from paid amount |
| −51 | Payment failed |
| −53 | Authority belongs to another merchant |
| −54 | Invalid authority |
| −60…−63 | Reverse errors (−62: server IP not set) |
| 100 / 101 | Success / already verified |

### D7.4 Payment tables
- **payments**: `order_id`, `gateway` (`zarinpal`), `amount` (Rial), `currency`, `authority` (unique), `status` (`pending`, `verified`, `failed`, `cancelled`, `reversed`, `refunded`), `ref_id`, `card_pan`, `card_hash`, `fee_type`, `fee`, `callback_status`, `verified_at`, `raw_request`, `raw_response`.
- **payment_refunds**: `payment_id`, `amount`, `method` (`reverse`, `panel`, `manual_transfer`), `status`, `reference`, `admin_id`.

### D7.5 Security rules
- Recalculate the amount from the order on the server; never trust amounts from the client.
- Verify inside a DB transaction with `SELECT … FOR UPDATE` on the payment row.
- The callback route is public but only reads `Authority`/`Status`; all trust comes from `verify`.
- The callback URL must be on the domain registered with Zarinpal (error −14).
- Do not store full card numbers.

### D7.6 Laravel design
```
app/Modules/Payments/
  Contracts/PaymentGateway.php   // request(Order): Redirect, verify(Payment, array $query): VerifyResult, reverse(Payment)
  Gateways/Zarinpal/ZarinpalClient.php
  Gateways/Zarinpal/ZarinpalGateway.php
  Gateways/Zarinpal/ZarinpalError.php // enum of codes above → Persian messages
app/Modules/Payments/Http/Controllers/PaymentController.php // start(), callback()
app/Modules/Payments/Jobs/ReconcileZarinpalPayments.php      // scheduled every 10 min
config/payments.php + .env: ZARINPAL_MERCHANT_ID, ZARINPAL_SANDBOX=true|false
app/Modules/Payments/Routes/web.php: GET /payment/zarinpal/callback
```
Mobile apps (phase 2): the app opens `StartPay` in an in-app browser or Custom Tab; the callback page deep-links back (`pharmacy://payment/result?order=…`) after the server has verified.

BNPL (Snapp Pay, Torob Pay, Digipay) is phase 2 behind the same `PaymentGateway` interface.

---

## D8. Images (summary of agreed rules; full device matrix in D14)

- Upload **one original** per image; the server generates sizes in a queued job.
- Product originals: ≥ 1200×1200, ≤ 5 MB, JPEG/PNG/WebP, square, white background; auto trim + pad to white square.
- Banners: desktop 1920×600, tablet 1536×768 and mobile 800×1000, uploaded separately (D14.2).
- Variants: widths per slot and device are defined in **D14.3 (authoritative)**; formats AVIF (q≈50), WebP (q≈75), JPEG (q≈80); EXIF stripped, sRGB.
- Placeholders: ThumbHash + dominant colour stored on the image; skeletons for grids; colour box for product thumbnails.
- File names contain a content hash; `Cache-Control: public, max-age=31536000, immutable`; CDN inside Iran.
- API returns `{ base, sizes, formats, thumbhash, color }` so each platform picks its own size.
- Targets: LCP < 2.5 s, CLS < 0.1, INP < 200 ms.

**Media storage:** spatie/laravel-medialibrary `media` table, plus custom properties `thumbhash`, `dominant_color`, `alt_fa`, `preset` (see D14). Equivalent fields if built without the package: `owner_type`, `owner_id`, `preset`, `original_path`, `width`, `height`, `base_path`, `widths` JSON, `formats` JSON, `thumbhash`, `dominant_color`, `alt_fa`, `status` (`processing`, `ready`, `failed`), `error`.

---

## D9. Scheduled jobs and queues

| Job | Schedule | Queue |
|---|---|---|
| ReconcileZarinpalPayments | every 10 min | `payments` |
| ExpirePendingPayments (30 min → payment_failed) | every 5 min | `payments` |
| PollTipaxTracking | every 30 min | `shipping` |
| TipaxSyncCities | weekly | `shipping` |
| RecalculateVariantPrice / RecalculatePrices (after supplier price, printed price, haraj, commission or import changes) | on demand + nightly | `pricing` |
| ParsePriceImport / ApplyPriceImport (supplier Excel) | on upload / on apply | `price-import` |
| MarkNearExpiry (expiry < 90 days) | daily | `default` |
| ProcessImage | on upload | `media` |
| SendSms (Melli Payamak) | on event | `notifications` |
| RefreshReportingWarehouse | 02:00 and 14:00 Tehran (twiceDaily) + weekly full rebuild Friday 03:00 | `reporting` |

Use Laravel Horizon for queue monitoring.

---

## D10. Open questions [CONFIRM list]

1. Tipax production API base URL, contract, wallet vs credit payment type, pickup schedule, COD settlement.
2. Tipax volumetric divisor and max weight/size per package.
3. Currency of Tipax `finalPrice` in `/Pricing`.
4. Tipax order callback payload, retry policy and signature; V3 webhook event payload.
5. Tipax rules for shipping supplements and OTC medicines (content type "داروهای مجاز").
6. Zarinpal refund API access after 30 minutes; Melli Payamak REST response JSON shape, credit-check method and PHP SDK package name (D11.6).
7. Licence of the location dataset (GPL-3.0) for a closed-source product.
8. Legal: partner pharmacy before enabling OTC / prescription products. [CONFIRM] OTC selling was enabled by the owner on 2026-11-07 (S10) ahead of this check; prescription products stay blocked.

---

## D11. Integration setup and configuration guide

Follow this section top to bottom when setting up a new environment (local, staging, production). Every value that differs between environments lives in `.env`; nothing is hard-coded.

### D11.1 Environment variables (`.env.example`)

```dotenv
APP_URL=https://www.example.ir
API_URL=https://api.example.ir

# ---------- Tipax ----------
TIPAX_ENABLED=true
TIPAX_BASE_URL=https://omtestapi.tipax.ir        # production: [CONFIRM] with Tipax
TIPAX_USERNAME=
TIPAX_PASSWORD=
TIPAX_API_KEY=
TIPAX_ORIGIN_ADDRESS_ID=                          # filled by: php artisan tipax:register-origin
TIPAX_DEFAULT_PAYMENT_TYPE=50                     # 50 wallet | 40 credit  [CONFIRM]
TIPAX_DEFAULT_PICKUP_TYPE=10                      # 10 pickup at our warehouse
TIPAX_DEFAULT_DISTRIBUTION_TYPE=10                # 10 deliver to address
TIPAX_WEBHOOK_SECRET=                             # random 64-char string
TIPAX_TIMEOUT_SECONDS=10
TIPAX_QUOTE_TTL_MINUTES=20
TIPAX_VOLUMETRIC_DIVISOR=5000                     # fallback estimate only [CONFIRM]

# ---------- Zarinpal ----------
ZARINPAL_MERCHANT_ID=                             # 36-char UUID from Zarinpal panel
ZARINPAL_SANDBOX=true                             # false in production
ZARINPAL_CURRENCY=IRR
ZARINPAL_CALLBACK_URL="${APP_URL}/payment/zarinpal/callback"
ZARINPAL_TIMEOUT_SECONDS=15
PAYMENT_PENDING_EXPIRY_MINUTES=30

# ---------- SMS: Melli Payamak (D11.6) ----------
SMS_DRIVER=melipayamak                            # log in local/testing
MELIPAYAMAK_USERNAME=
MELIPAYAMAK_API_KEY=
MELIPAYAMAK_BASE_URL=https://rest.payamak-panel.com
MELIPAYAMAK_PATTERN_OTP=                          # bodyId of approved pattern (other pattern ids in D11.6)

# ---------- Reporting warehouse (D22) ----------
REPORTING_DB_DATABASE=pharmacy_reporting
REPORTING_DB_USERNAME=
REPORTING_DB_PASSWORD=

# ---------- Images / CDN ----------
IMAGES_ORIGINALS_DISK=local
IMAGES_VARIANTS_DISK=cdn
IMAGES_CDN_URL=https://cdn.example.ir

# ---------- Queues ----------
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
```

Production rules: `APP_DEBUG=false`, secrets only in the server's environment or a secrets manager, never committed. Rotate `TIPAX_PASSWORD`, `TIPAX_API_KEY` and `TIPAX_WEBHOOK_SECRET` if a developer with access leaves.

### D11.2 Config files

`config/shipping.php`
```php
<?php

return [
    'default_carrier' => 'tipax',

    'carriers' => [
        'tipax' => [
            'enabled'           => (bool) env('TIPAX_ENABLED', true),
            'base_url'          => env('TIPAX_BASE_URL'),
            'username'          => env('TIPAX_USERNAME'),
            'password'          => env('TIPAX_PASSWORD'),
            'api_key'           => env('TIPAX_API_KEY'),
            'origin_address_id' => env('TIPAX_ORIGIN_ADDRESS_ID'),
            'payment_type'      => (int) env('TIPAX_DEFAULT_PAYMENT_TYPE', 50),
            'pickup_type'       => (int) env('TIPAX_DEFAULT_PICKUP_TYPE', 10),
            'distribution_type' => (int) env('TIPAX_DEFAULT_DISTRIBUTION_TYPE', 10),
            'webhook_secret'    => env('TIPAX_WEBHOOK_SECRET'),
            'timeout'           => (int) env('TIPAX_TIMEOUT_SECONDS', 10),
            'quote_ttl'         => (int) env('TIPAX_QUOTE_TTL_MINUTES', 20),
            'volumetric_divisor'=> (int) env('TIPAX_VOLUMETRIC_DIVISOR', 5000),
            'callbacks' => [
                'success' => env('API_URL').'/webhooks/tipax/order/success',
                'failure' => env('API_URL').'/webhooks/tipax/order/failure',
                'events'  => env('API_URL').'/webhooks/tipax/events',
            ],
        ],
        'own_courier' => [
            'enabled' => true,
        ],
    ],
];
```

`config/payments.php`
```php
<?php

return [
    'default' => 'zarinpal',

    'gateways' => [
        'zarinpal' => [
            'merchant_id'  => env('ZARINPAL_MERCHANT_ID'),
            'sandbox'      => (bool) env('ZARINPAL_SANDBOX', true),
            'currency'     => env('ZARINPAL_CURRENCY', 'IRR'),
            'callback_url' => env('ZARINPAL_CALLBACK_URL'),
            'timeout'      => (int) env('ZARINPAL_TIMEOUT_SECONDS', 15),
            'base_url'     => fn (bool $sandbox) => $sandbox
                ? 'https://sandbox.zarinpal.com'
                : 'https://payment.zarinpal.com',
        ],
    ],

    'pending_expiry_minutes' => (int) env('PAYMENT_PENDING_EXPIRY_MINUTES', 30),
];
```

Bindings in `ShippingServiceProvider` and `PaymentsServiceProvider`:
```php
$this->app->singleton(ShippingCarrier::class, fn ($app) => $app->make(TipaxCarrier::class));
$this->app->singleton(PaymentGateway::class, fn ($app) => $app->make(ZarinpalGateway::class));
```

### D11.3 Tipax: step-by-step setup

**A. Test account (do this first, on staging)**
1. Open https://omtest.tipax.ir/register and create a username and password.
2. Log in at https://omtest.tipax.ir/login and confirm the account details.
3. Go to **Profile → Business profile** and copy the **API key** (کلید اختصاصی).
4. Put `TIPAX_BASE_URL=https://omtestapi.tipax.ir`, `TIPAX_USERNAME`, `TIPAX_PASSWORD`, `TIPAX_API_KEY` in staging `.env`.
5. Run `php artisan tipax:ping`. It calls `POST /api/OM/v3/Account/token`, then `GET /api/OM/v4/Customers`, and prints the account name. Fix credentials until it passes.

**B. Reference data**
6. `php artisan tipax:sync-cities`: loads `Cities/plusstate` and `Cities/BranchCities` into `carrier_city_mappings` and lists unmatched cities. Resolve them in **Admin → Shipping → City mapping**.
7. `php artisan tipax:sync-reference`: loads services (`GetServicesList`), payment types, pack types, packings and package contents into local tables so the admin can pick them from dropdowns.
8. In **Admin → Shipping → Boxes**, create our cartons with inner dimensions and max weight, and link each to a Tipax `packingId` (e.g. "Carton size 4, 3-layer").
9. In **Admin → Shipping → Services**, enable the services we sell: e.g. 1 (ground one-day express), 2 (one-day), 3 (two-day), 7 (intra-city express), 12 (same day). Set display names in Persian and sort order.
10. Set default package content: **آرایشی، بهداشتی و داروهای مجاز**; liquids/fragile → **مایعات-شکستنی**.

**C. Origin (our warehouse)**
11. In **Admin → Shipping → Origins**, enter the warehouse address, city, postal code and map pin.
12. `php artisan tipax:register-origin {origin_id}`: calls `POST /api/OM/v4/Addresses`, saves the returned id in `origins.tipax_address_id`, and prints the value for `TIPAX_ORIGIN_ADDRESS_ID`.

**D. Webhooks**
13. Make sure `API_URL` is reachable from the internet over HTTPS (a valid certificate is required).
14. `php artisan tipax:register-webhook`: calls `POST /api/OM/v3/CustomerWebhook` with `{ url: <events url>, headers: [{ key: "X-Webhook-Secret", value: <TIPAX_WEBHOOK_SECRET> }] }`.
15. `php artisan tipax:webhook-history`: shows recent deliveries (`CustomerWebhookHistory`) to confirm Tipax reaches us.

**E. End-to-end test on staging**
16. Place a test order to a real city, pay in Zarinpal sandbox, move it to `packed`.
17. Confirm `RegisterTipaxShipment` ran: tracking codes saved on `shipment_packages`, success callback received.
18. Confirm `php artisan tipax:track {order_number}` returns status data.
19. Cancel the test parcel: `PUT /Parcels/Cancel/{trackingCode}` via **Admin → Order → Cancel shipment**.

**F. Go live**
20. Sign the contract with Tipax; ask for: production API base URL, production API access (`productionApiAccess = true`), payment type (wallet or credit), pickup times, COD settlement, volumetric rules.
21. Create the production account at https://etipaxco.com and get its API key.
22. Set production `.env`, run steps 5–15 again against production.
23. Send one real parcel to a colleague before opening to customers.

**Troubleshooting**

| Symptom | Likely cause |
|---|---|
| `UnAuthorized` | Token expired or wrong API key; clear `tipax:token` from Redis |
| `CityNotFound` / `CityCanNotBeNull` | City not mapped; run sync and fix mapping |
| `ServiceNotAvailable` | Service not offered on that route; check `GetServicesBetweenCities` |
| `SenderAddressIsRequired` / `ErrorInSenderAddress` | `TIPAX_ORIGIN_ADDRESS_ID` missing or wrong |
| `ParcelIsNotCancalable` / `ParcelIsPickedUp` | Already picked up; contact Tipax support |
| Quotes slow or failing | Timeout hit; checkout shows fallback rate flagged as estimate |

### D11.4 Zarinpal: step-by-step setup

**A. Sandbox (local and staging)**
1. Set `ZARINPAL_SANDBOX=true` and `ZARINPAL_MERCHANT_ID` to any UUID (e.g. from `php artisan tinker` → `Str::uuid()`).
2. Run `php artisan zarinpal:ping`: sends a 10,000 Rial request to the sandbox and prints the authority (starts with `S`).
3. Test the full flow in the browser: checkout → sandbox payment page → choose success or failure → callback → order status changes.
4. Test idempotency: refresh the callback page; the second verify must return code 101 and must not double-process the order.

**B. Production merchant**
5. Register a business account on Zarinpal and complete identity verification (the terminal needs at least silver level; lower levels return −16/−17).
6. Create a payment gateway (terminal) for the **exact production domain**; the callback URL must use this domain or you get −14.
7. Upload the site's e-Namad and any other documents Zarinpal asks for; wait for activation (−11 means not active yet).
8. In the gateway settings, set the **server IP** of the production app server (required for reverse; otherwise −62).
9. Copy the 36-character **merchant_id** into production `.env`, set `ZARINPAL_SANDBOX=false`.
10. Choose who pays the fee (merchant or customer) in the panel; the API returns it as `fee_type`.

**C. Go live checks**
11. Make one real small payment and verify: `payments.status = verified`, `ref_id` stored, order `paid`, SMS sent.
12. Reverse it with **Admin → Payment → Reverse** within 30 minutes and confirm status `reversed`.
13. Confirm the scheduler runs `ReconcileZarinpalPayments` (check `integration_logs`).

**Troubleshooting**

| Code | Fix |
|---|---|
| −9 | Missing field, description > 500 chars, or amount out of range |
| −10 | Wrong merchant_id or calls from a non-registered IP |
| −11 | Gateway not active yet; contact Zarinpal support |
| −14 | Callback domain ≠ registered domain |
| −41 | Amount above 100,000,000 Toman; split payment not supported |
| −50 | Verify amount differs; always verify with the stored payment amount |
| −62 | Server IP not set in gateway settings (reverse only) |

### D11.5 Location data setup
1. Download the latest release of `sajaddp/list-of-cities-in-Iran` (JSON).
2. Place files in `database/data/locations/` and run `php artisan locations:import`: fills `provinces`, `counties`, `cities`.
3. Optional: `composer require zarbinco/laravel-iran-locations` and run its sync to import Tehran regions and neighbourhoods, then `php artisan locations:import-tehran`.
4. Run `php artisan tipax:sync-cities` (D11.3 step 6) after every location import.
5. In **Admin → Locations**, enable delivery cities and draw own-courier zones.

### D11.6 SMS provider: Melli Payamak (ملی پیامک)

**Decision:** all SMS (OTP and order notifications) go through **Melli Payamak** (melipayamak.com), using its **shared service line with approved patterns** (خط خدماتی اشتراکی – پیامک پترن). Pattern messages are delivered even to numbers on the operators' advertising blacklist, which makes them right for OTP and transactional messages.

**How it works**
- In the Melli Payamak panel you create a **pattern** (predefined text) with variables, e.g. `کد ورود شما به داروخانه: {0}` and submit it for approval. Each approved pattern gets a numeric **bodyId**.
- To send, you call the API with the recipient, the bodyId and the variable values joined by `;` (e.g. `text = "48213"` or `"PH-140508-000123;3920418377"`). Only **one mobile number per call**.

**API (REST, from Melli Payamak docs, method SendByBaseNumber, last updated 22 Azar 1404)**
```
POST https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber
Content-Type: application/x-www-form-urlencoded

username=<panel username>&password=<API key>&text=<arg1;arg2>&to=09123456789&bodyId=<pattern id>
```
SOAP alternative: `http://api.payamak-panel.com/post/Send.asmx?wsdl`, method `SendByBaseNumber2` (same parameters). Official PHP SDK: `melipayamak/php` (`$api->sms()->sendByBaseNumber($text, $to, $bodyId)`) **[CONFIRM]** package name/version on Packagist; a small own HTTP client is equally fine.

**Return values (ReturnValue)**

| Value | Meaning |
|---|---|
| number with more than 15 digits (recId) | Sent successfully; use it to check delivery (GetDeliveries) |
| −111 | Caller IP not valid |
| −110 | Must use **ApiKey** instead of the password |
| −109 | Allowed IP must be set for API use |
| −108 | IP blocked after failed attempts |
| −10 | Variables contain a link |
| −7 / −6 | Sender line error / internal error: contact support |
| −5 | Text does not match the pattern's variables |
| −4 | bodyId wrong or pattern not approved |
| −3 | Sending line not defined |
| −2 | Only one mobile number allowed per call |
| −1 | Web service access disabled |
| 0 | Wrong username or password |
| 2 | Not enough credit |
| 6 | System updating |
| 7 | Text contains a filtered word |
| 10 / 12 | User not active / documents incomplete |
| 11 | Not sent |
| 16 / 18 | Recipient not found / invalid recipient |
| 17 | Empty text |
| 19 | Hourly limit exceeded |
| 35 | (REST) recipient is on the operators' blacklist |

**[CONFIRM]** the exact REST response JSON shape (e.g. `Value`, `RetStatus`, `StrRetStatus`) with a test account.

**Patterns to register (Persian texts, variables in order)**

| Template key | Example text | Variables |
|---|---|---|
| `otp` | کد ورود شما به داروخانه {0} است. این کد را در اختیار کسی قرار ندهید. | code |
| `order_paid` | سفارش {0} با موفقیت پرداخت شد. مبلغ: {1} تومان | order number, amount |
| `order_shipped` | سفارش {0} تحویل تیپاکس شد. کد رهگیری: {1} | order number, tracking code |
| `order_out_for_delivery` | سفارش {0} امروز توسط پیک ما تحویل می‌شود. | order number |
| `order_delivered` | سفارش {0} تحویل شد. از خرید شما سپاسگزاریم. | order number |
| `payment_failed` | پرداخت سفارش {0} ناموفق بود. می‌توانید دوباره تلاش کنید. | order number |
| `stock_available` | {0} دوباره موجود شد. | product name (short) |

Rules: no links inside variables (error −10); keep variables short; brand name inside the fixed pattern text.

**Configuration**
```dotenv
SMS_DRIVER=melipayamak            # log in local/testing
MELIPAYAMAK_USERNAME=
MELIPAYAMAK_API_KEY=              # use the panel API key as "password" (required: −110 otherwise)
MELIPAYAMAK_BASE_URL=https://rest.payamak-panel.com
MELIPAYAMAK_TIMEOUT_SECONDS=8
MELIPAYAMAK_PATTERN_OTP=          # bodyId
MELIPAYAMAK_PATTERN_ORDER_PAID=
MELIPAYAMAK_PATTERN_ORDER_SHIPPED=
MELIPAYAMAK_PATTERN_ORDER_OUT_FOR_DELIVERY=
MELIPAYAMAK_PATTERN_ORDER_DELIVERED=
MELIPAYAMAK_PATTERN_PAYMENT_FAILED=
MELIPAYAMAK_PATTERN_STOCK_AVAILABLE=
```
`config/sms.php` maps template keys → bodyIds; `notification_templates` stores the key, the Persian text (for display/logs) and the bodyId.

**Laravel design (module Notifications)**
```
app/Modules/Notifications/
  Contracts/SmsSender.php              // send(SmsMessageData $message): SmsResultData
  Data/SmsMessageData.php              // readonly: mobile, templateKey, args (list<string>)
  Data/SmsResultData.php               // readonly: success, providerMessageId, code, error
  Drivers/LogSmsSender.php             // local/testing: writes to log + notification_logs
  Drivers/MelipayamakSmsSender.php     // REST BaseServiceNumber, maps return codes to SmsResultData
  Enums/MelipayamakError.php           // codes above → Persian messages, retryable flag
  Actions/SendTemplatedSms.php         // takes SmsMessageData, resolves bodyId, logs, dispatches job
  Jobs/SendSmsJob.php                  // queue "notifications", retries 3× with backoff for retryable codes (6, 11, 19, timeouts)
```
- OTP is sent **synchronously-first** (queue `notifications` with high priority, timeout 8 s) so the code arrives within seconds; other templates are normal queued jobs.
- Every send writes `notification_logs` (mobile masked as 0912***6789, template, recId, code, duration).
- Non-retryable codes (−4, −5, −10, 0, 2, 7, 16, 18, 35) are not retried; credit-low (2) triggers an admin alert.
- A daily job checks the panel credit **[CONFIRM]** credit API method name and alerts below a threshold.

**Setup steps**
1. Create the Melli Payamak account, complete documents (error 12 otherwise), buy credit.
2. In the panel: get the **API key**, set the **allowed server IP** (errors −109/−111 otherwise).
3. Register and get approval for the 7 patterns above; copy each **bodyId** into `.env`.
4. `php artisan sms:test 09xxxxxxxxx otp` sends a test OTP and prints the return value.
5. Production: `SMS_DRIVER=melipayamak`; local/testing: `SMS_DRIVER=log`. Tests always use the `log` driver or `Http::fake()`.

**Security**: never log OTP codes in plain text (log `*****`), rate limits from D13.1 apply before calling the provider, API key only in `.env`.

### D11.7 Scheduler and workers (production server)
```bash
# crontab
* * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1

# supervisor: Horizon runs all queues
php artisan horizon
```
`routes/console.php`:
```php
Schedule::job(new ReconcileZarinpalPayments)->everyTenMinutes();
Schedule::job(new ExpirePendingPayments)->everyFiveMinutes();
Schedule::job(new PollTipaxTracking)->everyThirtyMinutes();
Schedule::command('tipax:sync-cities')->weeklyOn(6, '03:00');
Schedule::job(new MarkNearExpiry)->dailyAt('02:00');
```

### D11.8 Routes summary
```php
// app/Modules/Payments/Routes/web.php
Route::get('/payment/zarinpal/callback', [PaymentController::class, 'callback'])->name('payment.zarinpal.callback');

// app/Modules/Shipping/Routes/webhooks.php (loaded with the `api` middleware group, prefix none)
Route::post('/webhooks/tipax/order/{result}', TipaxOrderCallbackController::class)->whereIn('result', ['success', 'failure']);
Route::post('/webhooks/tipax/events', TipaxEventWebhookController::class)->middleware('tipax.webhook'); // checks X-Webhook-Secret
```
Exclude webhook routes from CSRF and rate-limit them (e.g. 120/min).

### D11.9 Go-live checklist
- [ ] Tipax production account, contract, API key, origin registered, webhook registered and tested
- [ ] Tipax city mapping reviewed; boxes linked to packing ids; services enabled
- [ ] Zarinpal gateway active on production domain; server IP set; one real payment and reverse tested
- [ ] Scheduler and Horizon running; failed-job alerts set up
- [ ] `integration_logs` redact secrets; error tracking (e.g. Sentry) connected
- [ ] Melli Payamak patterns approved, bodyIds in `.env`, server IP allowed, credit alert working
- [ ] e-Namad on the site

## D12. Database schema conventions, indexes and migrations

### D12.1 Conventions
- InnoDB, `utf8mb4`, collation `utf8mb4_unicode_ci` (switch to `utf8mb4_persian_ci` for name columns sorted in Persian).
- Primary keys `bigIncrements('id')`; foreign keys `foreignId('x_id')->constrained()->cascadeOnUpdate()` with `restrictOnDelete()` for business data, `cascadeOnDelete()` only for pure child rows (cart_items, bundle_items, pivots).
- Money: `unsignedBigInteger` Rial. Percent: `decimal(5,2)`. Weights/dimensions: `unsignedInteger` (g, mm).
- Enums: `string(32)` column + PHP backed enum cast (not MySQL ENUM, so new values need no ALTER).
- JSON: `json` columns for snapshots and raw payloads; never query inside them on hot paths.
- Soft deletes: catalog, content, coupons, customers. Never on orders, payments, shipments (use status).
- Timestamps in UTC; display in Asia/Tehran Jalali.
- Sensitive columns with `encrypted` cast: `customers.national_id`, `carriers.credentials`, `integration_logs.request` (after redaction).
- Migrations live in each module: `app/Modules/{Module}/Database/Migrations/YYYY_MM_DD_HHMMSS_create_x_table.php`; order across modules is controlled by timestamps (Locations → Identity → Catalog → Pricing → Cart → Orders → Payments → Shipping → Content → Settings → Seo → Search → Notifications → System).

### D12.2 Required indexes

| Table | Index |
|---|---|
| categories | unique(slug); index(parent_id, sort); index(is_active) |
| brands | unique(slug); index(is_active) |
| products | unique(slug); index(brand_id); index(primary_category_id); index(status, product_type); fulltext(name_fa, name_en) |
| product_variants | unique(sku); index(product_id); index(barcode); index(is_active, stock_status); index(is_near_expiry); index(is_damaged_box); index(expiry_date) |
| provider_prices | unique(provider_id, variant_id); index(variant_id, is_available, cost) |
| pricing_rules | index(scope_type, scope_id, is_active, priority) |
| customers | unique(mobile) |
| otp_codes | index(mobile, expires_at) |
| addresses | index(customer_id, is_default) |
| carts | unique(session_token); index(customer_id); index(expires_at) |
| cart_items | unique(cart_id, variant_id) |
| coupons | unique(code); index(is_active, starts_at, ends_at) |
| coupon_usages | index(coupon_id, customer_id) |
| orders | unique(number); index(customer_id, placed_at); index(status, placed_at); index(payment_status) |
| order_items | index(order_id); index(variant_id); index(provider_id) |
| order_status_logs | index(order_id, created_at) |
| order_item_status_logs | index(order_item_id, created_at) |
| payments | unique(authority); index(order_id); index(status, created_at) |
| shipments | index(order_id); index(status, last_synced_at); index(correlation_id) |
| shipment_packages | unique(tracking_code) |
| carrier_city_mappings | unique(carrier_id, carrier_city_id); index(carrier_id, city_id) |
| cities | index(province_id); index(county_id); index(is_delivery_enabled) |
| flash_deals | index(starts_at, ends_at) |
| search_logs | index(normalized_query, created_at) |
| integration_logs | index(service, operation, created_at); index(order_id) |

### D12.3 Example migration (pattern every module follows)
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->index();
            $table->string('title_fa');
            $table->unsignedBigInteger('official_price')->nullable();   // Rial
            $table->unsignedBigInteger('price');                        // Rial
            $table->unsignedBigInteger('sale_price')->nullable();       // Rial
            $table->string('stock_status', 32)->default('available');
            $table->date('expiry_date')->nullable()->index();
            $table->boolean('is_near_expiry')->default(false)->index();
            $table->boolean('is_damaged_box')->default(false)->index();
            $table->unsignedInteger('weight_g')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->boolean('dimensions_verified')->default(false);
            $table->boolean('ship_separately')->default(false);
            $table->boolean('is_fragile')->default(false);
            $table->boolean('is_liquid')->default(false);
            $table->boolean('requires_cold_chain')->default(false);
            $table->unsignedSmallInteger('max_per_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'stock_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
```

### D12.4 Models and relationships (summary)

| Model | Relations |
|---|---|
| Category | parent(), children(), products() (belongsToMany), primaryProducts() |
| Brand | products() |
| Product | brand(), primaryCategory(), categories(), variants(), attributeValues(), tags(), faqs(), relations(), reviews(), questions(), media (gallery) |
| ProductVariant | product(), providerPrices(), cartItems(), orderItems(), flashDeals() |
| Provider | prices(), purchases() |
| Customer | addresses(), orders(), carts(), wishlist(), reviews() |
| Order | customer(), items(), statusHistories(), payments(), shipments(), coupon(), providerPurchases(), returnRequests() |
| OrderItem | order(), variant(), provider() |
| Payment | order(), refunds() |
| Shipment | order(), carrier(), service(), packages(), events() |
| City | province(), county(), regions(), carrierMappings() |

Casts: enums (`ProductType`, `ProductStatus`, `StockStatus`, `OrderStatus`, `PaymentStatus`, `ShipmentStatus`, `CouponType`), `immutable_date` for `expiry_date`, `array` for JSON snapshots, `encrypted` for sensitive fields.

---

## D13. Business and system flows

### D13.1 OTP login
1. Customer enters mobile (normalised to `09xxxxxxxxx`, Persian digits converted).
2. `Identity\Actions\SendOtp`: rate limit (1/2 min per mobile, 5/hour per IP, 10/day per mobile); create 5-digit code, store hash, expiry 2 min; dispatch `SendSms` (template `otp`).
3. Customer enters code (TallStackUI pin). `VerifyOtp`: max 5 attempts, constant-time compare, then find-or-create customer, `Auth::login`, regenerate session, merge guest cart.
4. New customer → ask first/last name (optional, skippable).

### D13.2 Browse and search
1. PLP query object builds filters from query string (`?brand[]=…&form[]=…&price=…&in_stock=1&sort=popular`); results cached per filter hash for 5 min (Redis tags `catalog`).
2. Search: normalise Persian (ی/ي, ک/ك, digits, ZWNJ), expand synonyms, Scout database driver (MySQL fulltext) → log in `search_logs`.
3. Zero results → show popular tags/categories + "notify me" option.

### D13.3 Cart
1. Add to cart: validates variant active, not `requires_prescription`, not cold-chain, qty ≤ max_per_order; price snapshot taken.
2. Cart recalculates on every change: current price, flash deal, coupon, free-shipping progress.
3. Guest cart keyed by `session_token` cookie (30 days); merged on login.

### D13.4 Checkout
1. Step 1 Address: select/create (map pin, city from Locations; Tipax only if city mapped).
2. Step 2 Shipping: `Shipping\Actions\QuoteCart` packs items into boxes (D5), calls carriers: Tipax (`GetServicesBetweenCities` + `Pricing`) and own courier (zone price + slots). Shows options with delivery days and price. Quote stored 20 min.
3. Step 3 Review: totals recalculated server-side; coupon re-validated.
4. Step 4 Pay: `Orders\Actions\PlaceOrder` (transaction): create order `pending_payment` with snapshots, lock coupon usage, then `Payments\Actions\StartPayment` → Zarinpal request → redirect.

### D13.5 Payment
See D7.2. On verify success: `OrderPaid` event → (a) Orders: status `paid`; (b) Pricing: create provider purchase draft; (c) Notifications: SMS `order_paid`; (d) Coupons: confirm usage. On NOK/timeout: `payment_failed`, release coupon.

### D13.6 Sourcing (buying from provider)
1. Admin → Provider purchases: system groups paid orders' items by cheapest available provider into a draft purchase.
2. Operator sends the purchase (Excel/print/WhatsApp to Sina's company) → status `requested` → `confirmed`.
3. Goods arrive → operator ticks received quantities → order items fully received → order `ready_to_pack`.
4. Missing items → admin chooses: wait, substitute (customer consent by phone), or partial refund (Zarinpal reverse if < 30 min, else panel refund).

### D13.7 Packing and shipping
1. Operator opens order → system suggests boxes; operator confirms real weight → `packed`.
2. `RegisterTipaxShipment` job (idempotent on shipment id) → Tipax order with callbacks → `awaiting_pickup` with tracking codes; label printed from admin.
3. Own courier: assign courier + slot → `out_for_delivery`.
4. Tipax webhooks / polling update `in_transit` → `delivered`; SMS on each customer-facing change.

### D13.8 Cancellation, refund, return
- Customer may cancel before `packed` (account page) → refund.
- After delivery: return request within 7 days (sealed cosmetics/supplements per policy) → admin approves → Tipax return order (`ReturnRegisterOrder`) → received → refund.

### D13.9 Provider price import
1. Admin uploads provider Excel (columns: SKU/barcode, name, cost, discount %, available).
2. Job parses with OpenSpout, matches by barcode → SKU → name similarity (flagged), shows **preview diff** (old cost, new cost, price change %).
3. Admin applies → provider_prices updated (job on `price-import`) → `RecalculatePrices` (on `pricing`) writes new price records with the engine (D3.2); changes > 15 % are flagged in the price history.

### D13.10 Image upload
Admin uploads original → validation (D14.2) → medialibrary stores original privately → queued conversions per preset → ThumbHash + dominant colour saved as custom properties → status `ready`.

### D13.11 Other flows
- Stock alert: customer subscribes on out-of-stock variant → when `stock_status` becomes `available`, SMS sent once.
- Near expiry: daily job flags variants with `expiry_date` < 90 days → appear in "زودمصرف".
- Review moderation: reviews `pending` → admin approves → visible; verified-buyer badge if linked to delivered order item.
- Contact form: stored + email/SMS alert to operator.

---

## D14. Image optimisation for desktop, tablet and mobile

### D14.1 Device classes

| Class | CSS width | Typical DPR | Breakpoint |
|---|---|---|---|
| Mobile | 360–767 px | 2–3 | default |
| Tablet | 768–1279 px | 2 | `md:` 768 |
| Desktop | ≥ 1280 px | 1–2 | `xl:` 1280 |

### D14.2 Upload rules (originals)

| Preset | Min size | Ratio | Max file | Formats in |
|---|---|---|---|---|
| product | 1200×1200 | 1:1 (auto trim + pad white) | 5 MB | JPEG, PNG, WebP |
| banner_desktop | 1920×600 | 16:5 | 5 MB | JPEG, PNG, WebP |
| banner_tablet | 1536×768 | 2:1 | 5 MB | JPEG, PNG, WebP |
| banner_mobile | 800×1000 | 4:5 | 5 MB | JPEG, PNG, WebP |
| category_icon | SVG preferred, else 256×256 | 1:1 | 500 KB | SVG, PNG |
| brand_logo | 400×200 (transparent) | 2:1 | 1 MB | SVG, PNG, WebP |
| category_banner_desktop | 1920×480 (also used on tablet) | 4:1 | 5 MB | JPEG, PNG, WebP |
| category_banner_mobile | 800×600 | 4:3 | 5 MB | JPEG, PNG, WebP |
| article_cover | 1200×675 | 16:9 | 5 MB | JPEG, PNG, WebP |
| prescription (P2) | any, compressed client-side to 2000 px | — | 8 MB | JPEG, PNG, HEIC, PDF |

### D14.3 Generated sizes per slot and device

| Slot | Mobile (rendered → file) | Tablet | Desktop | Generated widths | Budget per file (WebP/AVIF) |
|---|---|---|---|---|---|
| Product card (PLP grid) | 2 cols ≈ 170 px → 340/510 | 3–4 cols ≈ 220 px → 440 | 5 cols ≈ 240 px → 240/480 | 160, 320, 480, 640 | ≤ 25 KB at 320 |
| Cart / mini-cart thumb | 64 px → 128/192 | 80 → 160 | 80 → 80/160 | 80, 160, 240 | ≤ 8 KB |
| PDP main image | 100vw ≈ 390 → 780/1170 | ≈ 600 → 1200 | ≈ 560 → 560/1120 | 400, 800, 1200 | ≤ 90 KB at 800 |
| PDP zoom | — | 1600 | 1600 | 1600 | ≤ 180 KB |
| Hero banner | 800×1000 art | 1536×768 art | 1920×600 art | mobile 480, 800 · tablet 1024, 1536 · desktop 1280, 1920 | mobile ≤ 90 KB, tablet ≤ 140 KB, desktop ≤ 180 KB |
| Category icon | 48 px | 56 px | 64 px | SVG (or 96, 192 PNG/WebP) | ≤ 6 KB |
| Brand logo | 96×48 | 120×60 | 160×80 | 160, 320 | ≤ 10 KB |
| Category banner | 100vw (800×600 art) | 100vw (1920×480 art) | 1216 px (1920×480 art) | mobile 480, 800 · desktop 768, 1280, 1920 | mobile ≤ 90 KB, desktop ≤ 180 KB |
| Article cover (P2) | 100vw | 50vw | 33vw | 400, 800, 1200 | ≤ 80 KB at 800 |
| OG/social share | — | — | 1200×630 | 1200 | ≤ 150 KB JPEG |

### D14.4 Formats and quality
- Output **AVIF** (quality 50) + **WebP** (quality 75) + **JPEG** fallback (quality 80, progressive). Logos/icons with transparency: **SVG**, else WebP lossless.
- Strip EXIF/GPS, convert to sRGB, auto-orient.
- ThumbHash (≤ 100 px source) + dominant colour stored per media item (ThumbHash only, no BlurHash: confirmed 2026-11-07).
- Uploaded SVG (logos, icons) is sanitized with `rhukster/dom-sanitizer` (MIT) and served as-is with a restrictive CSP; `enshrined/svg-sanitize` is not used because it is GPL.
- File names with content hash; served from CDN with `Cache-Control: public, max-age=31536000, immutable`.

### D14.5 Rendering rules
- `<x-media.picture :media="$m" slot="product-card" />` Blade component outputs `<picture>` with AVIF/WebP `srcset`, `sizes` per slot, JPEG `<img>` fallback, `width`/`height`, `loading="lazy"`, `decoding="async"`, Persian `alt`.
- Hero (LCP) image: no lazy, `fetchpriority="high"`, `<link rel="preload" as="image" imagesrcset=… imagesizes=…>`.
- Placeholders: skeleton for whole sections (TallStackUI loading/skeleton or Tailwind `animate-pulse`), dominant-colour box for product cards, ThumbHash for banners; fade-in 200 ms.
- Carousels load the visible slide + next only.

### D14.6 medialibrary configuration (example)
```php
public function registerMediaConversions(?Media $media = null): void
{
    foreach ([160, 320, 480, 640, 800, 1200, 1600] as $w) {
        foreach (['avif' => 50, 'webp' => 75, 'jpg' => 80] as $format => $quality) {
            $this->addMediaConversion("w{$w}-{$format}")
                ->performOnCollections('gallery')
                ->width($w)
                ->format($format)
                ->quality($quality)
                ->optimize()
                ->queued();
        }
    }
}
```
(Exact widths per collection come from `config/media-presets.php`; generate only what each slot needs.)

---

## D15. Routes, Livewire components and Filament resources

### D15.1 Storefront routes (`web` middleware, Persian slugs allowed)

| Method | URI | Handler | Name |
|---|---|---|---|
| GET | `/` | `content::home-page` | home |
| GET | `/categories` | `catalog::category-index` | categories.index |
| GET | `/categories/{slug}` | `catalog::category-page` | category.show |
| GET | `/brands` | `catalog::brand-index` | brands.index |
| GET | `/brands/{slug}` | `catalog::brand-page` | brands.show |
| GET | `/collections` | `catalog::collection-index` | collections.index |
| GET | `/collections/{slug}` | `catalog::collection-page` | collections.show |
| GET | `/t/{tag:slug}` | `Catalog\Livewire\TagPage` | tags.show |
| GET | `/search` | `Search\Livewire\SearchPage` | search |
| GET | `/products/{slug}` | `catalog::product-page` | products.show |
| GET | `/b`, `/b/{slug}`, `/c/{slug}`, `/p/{slug}` | 301 to the full-word addresses (S13b) | — |
| GET | `/deals` | `Content\Livewire\DealsPage` | deals |
| GET | `/bundles/{bundle:slug}` | `Catalog\Livewire\BundlePage` | bundles.show |
| GET | `/cart` | `Cart\Livewire\CartPage` | cart |
| GET | `/checkout` | `Checkout\Livewire\CheckoutWizard` (auth) | checkout |
| GET | `/payment/zarinpal/callback` | `Payments\Http\Controllers\PaymentController@callback` | payment.zarinpal.callback |
| GET | `/payment/result/{order:number}` | `Payments\Livewire\PaymentResult` (auth) | payment.result |
| GET | `/login` | `Identity\Livewire\OtpLogin` (guest) | login |
| POST | `/logout` | `Identity\Http\Controllers\LogoutController` | logout |
| GET | `/account` | `Identity\Livewire\Account\Dashboard` (auth) | account |
| GET | `/account/orders` | `Orders\Livewire\Account\OrderList` | account.orders |
| GET | `/account/orders/{order:number}` | `Orders\Livewire\Account\OrderDetail` | account.orders.show |
| GET | `/account/addresses` | `Identity\Livewire\Account\Addresses` | account.addresses |
| GET | `/account/wishlist` | `Catalog\Livewire\Account\Wishlist` | account.wishlist |
| GET | `/account/profile` | `Identity\Livewire\Account\Profile` | account.profile |
| GET | `/track` | `Orders\Livewire\GuestTracking` | track |
| GET | `/pages/{page:slug}` | `Content\Livewire\StaticPage` | pages.show |
| GET | `/faq` | `Content\Livewire\FaqPage` | faq |
| GET | `/contact` | `Content\Livewire\ContactPage` | contact |
| GET | `/pharmacist` | `Settings\Livewire\PharmacistPage` | pharmacist |
| GET | `/sitemap.xml`, `/robots.txt` | `Seo` controllers | |

Webhooks (no CSRF, rate-limited, secret header): `POST /webhooks/tipax/order/{result}`, `POST /webhooks/tipax/events`.
Admin: Filament panel `/admin` (separate guard `admin`, MFA required).

### D15.2 Shared Livewire / Blade components
Names below are written `module.component` for readability; in code a module component is referenced as `module::component` (C3 rule 5), e.g. `catalog.product-card` → `catalog::product-card`.
`layout.app` (header with search, mega menu, mini-cart, bottom nav), `search.instant-box`, `catalog.product-card`, `catalog.quick-view`, `catalog.filters` (desktop sidebar / mobile slide), `catalog.sort`, `catalog.variant-picker`, `cart.mini-cart`, `cart.add-button`, `content.flash-deal-countdown`, `content.banner-slider`, `content.chips`, `checkout.address-form` (map), `checkout.shipping-options`, `orders.tracking-timeline`, `media.picture`, `ui.price`, `ui.badge-*` (discount, near-expiry, damaged-box, rx, original).

### D15.3 Filament resources (per module)
Catalog: ProductResource (tabs: general, content, SEO, media; relation managers: Variants, FAQs, Relations, Tags), CategoryResource (tree reorder), BrandResource, AttributeResource, TagResource, BundleResource, ReviewResource, ProductQuestionResource.
Pricing: ProviderResource, ProviderPriceResource, PricingRuleResource, PriceImportResource (custom page: upload → preview → apply).
Orders: OrderResource (view page with timeline, state actions: mark sourcing/received/packed, register shipment, cancel, refund), ProviderPurchaseResource, ReturnRequestResource.
Payments: PaymentResource (read-only + reverse action), PaymentRefundResource.
Cart: CouponResource.
Shipping: ShipmentResource, CarrierServiceResource, ShippingBoxResource, OriginResource, CarrierCityMappingResource (bulk fix), DeliveryZoneResource, DeliverySlotResource, CourierResource, ShippingRuleResource.
Content: BannerResource, HomeSectionResource (reorder), FlashDealResource, PageResource, FaqResource, MenuResource, ContactMessageResource.
Identity: CustomerResource, AdminUserResource, RoleResource (provided by Filament Shield).
Settings: GeneralSettings page (spatie/laravel-settings), SocialLinkResource, TrustBadgeResource, PharmacistResource, SeoRedirectResource, NotificationTemplateResource.
System: ActivityLogResource, IntegrationLogResource, Health page.
Widgets: SalesStats, OrdersByStatus, SourcingBacklog, ShipmentsInTransit, MarginTrend, IntegrationErrors.

---

## D16. Security

| Area | Rule |
|---|---|
| Admin access | Separate `admin` guard and `admin_users` table; **Filament MFA (TOTP) mandatory**; strong passwords; session timeout 30 min idle; optional IP allowlist for `/admin` in Nginx |
| Authorization | spatie/laravel-permission roles (`super_admin`, `operator`, `content_editor`, `pharmacist` P2); a Policy for every Filament resource; deny by default |
| Customer auth | **Laravel Sanctum** (decided 2026-11-06): storefront customers authenticate through Sanctum (stateful, cookie-based for the Livewire storefront and its AJAX endpoints; the same setup issues tokens for the P2 `/api/v1` apps); exact guard setup confirmed in the S17 plan. OTP only; rate limits (D13.1); codes hashed; session regenerated on login; `remember` cookie 30 days, `secure`, `httponly`, `samesite=lax` |
| CSRF | On all web forms (Livewire handles it); excluded only for webhooks |
| Input | Validation on every input; Persian digit normalisation; rich text sanitized (purifier) before save; no raw `{!! !!}` except sanitized HTML |
| Mass assignment | No `$fillable` and never `$guarded = []`: models stay fully guarded and are written only through setters and `save()` (decided 2026-11-07) |
| SQL | Eloquent/Query Builder bindings only; no string-built SQL |
| Files | Validate MIME by content, size limits, re-encode images (strips payloads), random file names, originals on private disk, SVG sanitized (only admins upload SVG) |
| Payments | Amount computed server-side; verify with Zarinpal before marking paid; verify idempotent (100/101); `lockForUpdate`; callback domain registered; never trust `Status=OK` alone |
| Webhooks | Secret header compared with `hash_equals`; payload stored raw; processed in queue; replay-safe (idempotent by tracking code + event) |
| Secrets | Only in `.env`/server secrets; `encrypted` casts for stored credentials; redaction middleware for logs (password, apiKey, token, merchant_id, card) |
| Transport | HTTPS only, HSTS (1 year, includeSubDomains), TLS 1.2+ |
| Headers | CSP (self + CDN + Zarinpal + map tiles), `X-Frame-Options: DENY` (except none needed), `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` |
| Rate limiting | `throttle` on login/OTP, search (60/min), cart (120/min), contact form (5/hour), webhooks (120/min) |
| Privacy | Collect minimum data; national ID optional + encrypted; customer data export/delete on request; addresses visible only to owner and authorised staff |
| Dependencies | `composer audit` and `npm audit` in CI; Dependabot/Renovate |
| Logging & audit | activitylog on all admin writes (prices, orders, refunds, settings) |
| Backups | Daily DB + weekly media backups (spatie/laravel-backup) to separate storage; restore test monthly |
| Errors | `APP_DEBUG=false` in production; Sentry with PII scrubbing |

---

## D17. Performance and caching
- Redis cache for: settings, menus (tags `content`; the home page is not cached, S13b), category tree, PLP filter facets, PDP view model (tag `product:{id}`); invalidate via model events.
- Eager loading everywhere (`preventLazyLoading()` in non-production).
- `php artisan optimize` on deploy (config, route, view, event cache); OPcache + JIT on.
- Livewire: `#[Lazy]` for below-the-fold sections, `wire:model.live.debounce.300ms` for search, avoid big public properties (pass ids, not models).
- Pagination: numbered pages on desktop and «نمایش بیشتر» (next 24) on phones and tablets, with `?page=` in the URL (decided 2026-11-07 in S12 instead of cursor pagination, which cannot show page numbers or sort by price well).
- Queue everything slow (SMS, images, Tipax calls, price recalculation).
- HTTP: Brotli/gzip, HTTP/2, CDN for static assets with hashed names (Vite).

---

## D18. Testing strategy (Pest 4)

| Level | What | Tools |
|---|---|---|
| Unit | Actions (called with DTOs), DTO construction/mapping, value objects (Money, Jalali), pricing engine, box packer, Persian normaliser, enums | Pest |
| Request & Resource | Form Request rules, authorisation and `prepareForValidation()` normalisation; `toDto()` output; API Resource JSON shape (`assertJsonStructure`) | Pest |
| Feature | HTTP routes, Livewire components (`Livewire::test`), Filament resources (`livewire(ListProducts::class)->assertCanSeeTableRecords(...)`), policies, jobs, events | Pest + Laravel/Livewire/Filament helpers |
| Integration (faked) | Tipax client and Zarinpal client with `Http::fake()` using recorded fixtures from the sandbox | Pest |
| Architecture | Module boundaries (`arch()->expect('App\Modules\Catalog')->not->toUse('App\Modules\Orders\Models')`), strict types, no `env()` outside config, no debug functions | Pest arch |
| Browser (smoke) | Home → PDP → cart → checkout (sandbox) on mobile + desktop viewports | Pest browser plugin (Playwright) |

Rules:
- Every step in Part E ships tests; a step is not done until `php artisan test --parallel` is green, Pint passes, Larastan passes.
- Factories for every model; seeders for demo data (`DemoCatalogSeeder`: 30 products, 5 brands, 8 categories).
- Coverage target: ≥ 80 % for `Actions/` and integration clients; critical flows (payment verify, order state machine, pricing engine) 100 % branch coverage.
- Never call real Tipax/Zarinpal in automated tests; sandbox checks are manual steps in the step's acceptance list.

Example test (payment verify idempotency):
```php
it('treats Zarinpal code 101 as already verified without double-processing', function () {
    Http::fake([
        '*/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 101, 'ref_id' => 201], 'errors' => []]),
    ]);
    $payment = Payment::factory()->verified()->create();

    app(VerifyPayment::class)($payment, ['Authority' => $payment->authority, 'Status' => 'OK']);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Verified);
    Event::assertNotDispatched(OrderPaid::class);
});
```

---

## D19. DevOps

### D19.1 Environments
`local` (Laravel Herd/Sail, Zarinpal sandbox, Tipax test), `staging` (same as prod, test credentials), `production`.

### D19.2 Server (production, MVP)
- 1 app server (4 vCPU, 8 GB) Ubuntu 24.04: Nginx, PHP-FPM 8.5 (+ imagick with AVIF/HEIF, intl, redis, gd, bcmath, exif), Supervisor, Node only on CI.
- MySQL 8.4 (managed or separate 2 vCPU/4 GB), Redis 7 (can be on app server for MVP).
- Object storage + ArvanCloud CDN for media; hosting inside Iran.

### D19.3 Supervisor (Horizon)
```ini
; /etc/supervisor/conf.d/horizon.conf
[program:horizon]
process_name=%(program_name)s
command=php /var/www/app/current/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/app/storage/logs/horizon.log
stopwaitsecs=3600
```
`config/horizon.php` supervisors: `payments` (priority, 2 processes), `shipping` (2), `notifications` (2), `media` (2, memory 512 MB, timeout 300), `default,pricing` (2). `pricing` (price calculations, D3.2), `price-import` (supplier Excel imports, own supervisor, 1 process), `search` (Scout indexing): **1 process**, so a product's updates reach Meilisearch in order (decided 2026-11-09, S13b: parallel workers let an older snapshot overwrite a newer one).

Cron: `* * * * * www-data php /var/www/app/current/artisan schedule:run >> /dev/null 2>&1`.

### D19.4 Deploy (zero-downtime)
`git pull` (or release folder) → `composer install --no-dev --optimize-autoloader` → `npm ci && npm run build` (CI) → `php artisan migrate --force` → `php artisan optimize` → `php artisan filament:optimize` → `php artisan horizon:terminate` → reload PHP-FPM. Tools: Laravel Envoy or Deployer.

### D19.5 CI (GitHub/GitLab)
On every push: Pint (test mode), Larastan, `composer audit`, `php artisan test --parallel` (MySQL + Redis services), `npm run build`. On `main`: deploy to staging. Production deploy is manual approval.

---

## D20. Observability
- Sentry (errors), Laravel Pulse (slow queries, jobs, requests), Horizon (queues), spatie/laravel-health (checks + Telegram/email alerts), `integration_logs` dashboard widget, uptime monitor on `/up`.
- Alerts: payment verify failures > 3 in 10 min, Tipax registration failures, queue wait > 60 s, disk > 80 %.

## D21. Search engine (MySQL FULLTEXT → Meilisearch hybrid)

> **Update (2026-11-07, S12, owner's decision):** the MVP uses **Meilisearch from the start** (self-hosted Docker service `getmeili/meilisearch:v1.55.1`, Scout `meilisearch` driver) for search **and** for the category/brand/tag listings with filter counts; Phase 1's MySQL FULLTEXT path is not built. Semantic (hybrid) search stays for later. If Meilisearch is unreachable, listings fall back to the database (newest first, page scope and brands only, no counts) and search says it is temporarily unavailable. Synonyms can also be imported from Excel/CSV in the admin (merging into existing phrases). The sections below keep the original plan for reference.
>
> **Decision (original):** MVP uses **MySQL FULLTEXT** through Laravel Scout's `database` driver. Phase 2 moves to self-hosted **Meilisearch** with **hybrid (keyword + vector) search** using a **local multilingual embedding model**. "Similar products" is computed in a nightly batch and stored in MySQL in both phases. Search controllers/Livewire follow C5 (Form Request → DTO → Action → API Resource).

### D21.1 Summary

| | MVP (Phase 1) | Phase 2 |
|---|---|---|
| Engine | MySQL 8.4 InnoDB FULLTEXT | Meilisearch (self-hosted, open source) |
| Laravel integration | Laravel Scout, `database` driver | Laravel Scout, `meilisearch` driver |
| Typo tolerance | No (synonyms + normalisation only) | Yes, built in |
| Autocomplete | Prefix match (`word*`) on name, brand, SKU | Instant search-as-you-type |
| Facets with counts | SQL `GROUP BY` (cached 5 min) | Built-in facet distribution |
| Semantic search | No | Hybrid search, `semanticRatio` ≈ 0.4–0.5 |
| Similar products | Nightly batch → `product_similarities` table | Same (optionally Meilisearch similar-documents) |
| Extra cost | 0 | 0 software cost; a 2–4 GB RAM VPS or the app server |
| When to switch | — | > 3,000 active products, or search-exit rate > 40 %, or zero-result rate > 10 % |

Why not others:

- **Algolia / foreign SaaS**: paid, hosted outside Iran, accounts and payments can be blocked by sanctions.
- **Elasticsearch / OpenSearch**: powerful but needs 4–8 GB RAM and operational effort; not needed at this scale.
- **Typesense**: good alternative with vector search; Meilisearch chosen for its official Laravel Scout driver, simpler setup and larger Laravel community.
- **Vector search inside MySQL**: MySQL 8.4 has no vector type. MySQL 9.x adds `VECTOR`, but Oracle's manual states `DISTANCE()` "is available only for users of MySQL HeatWave on OCI and MySQL AI; it is not included in MySQL Commercial or Community distributions", and a VECTOR column "cannot be used as any type of key". So live vector search in MySQL Community is not practical.

---

### D21.2 Search requirements

1. Search Persian and English product names, brand names (Persian and Latin), active ingredients, SKU and barcode.
2. Treat these as equal: Arabic/Persian letters (ي/ی, ك/ک, ة/ه, أ/إ/ا), Persian/Arabic/Latin digits, ZWNJ (نیم‌فاصله) vs space vs none, diacritics.
3. Synonyms: «ویتامین دی» = «ویتامین D» = «D3»; «امگا ۳» = «omega 3» = «روغن ماهی»; «ضد آفتاب» = «کرم ضدآفتاب» = «sunscreen».
4. Filters: category, brand, dosage form, suitable for (age/gender), price range, in stock, discount %, near-expiry, damaged-box.
5. Sort: relevance, best selling, newest, cheapest, most expensive, highest discount.
6. Autocomplete under the search box: up to 6 products, 3 brands, 3 categories, 3 tags, in < 150 ms.
7. Zero-result page with popular tags/categories and logging of the query.
8. Only active, sellable variants appear; prescription products appear with their info badge but cannot be added to cart in MVP.

---

### D21.3 Persian text normalisation (both phases)

Applied identically when **indexing** and when **querying** (class `Search\Support\PersianNormalizer`):

| Step | Example |
|---|---|
| Arabic → Persian letters | `ي` → `ی`, `ك` → `ک`, `ة` → `ه`, `أ إ آ` → `ا` (keep `آ` in display text only) |
| Digits → Latin | `۱۲۳` / `١٢٣` → `123` |
| Remove diacritics and tatweel | `ـ`, `َ ُ ِ ّ ً` removed |
| ZWNJ handling | index both forms: `ضدآفتاب` and `ضد آفتاب` (ZWNJ → space, plus a joined variant) |
| Lowercase Latin, trim, collapse spaces | `Vitamin  D3` → `vitamin d3` |
| Unit normalisation | `۱۰۰۰ واحد`, `1000IU`, `1000 iu` → `1000 iu` |

Unit test cases are part of S12 (see S12).

---

### D21.4 Phase 1: MySQL FULLTEXT with Laravel Scout

#### D21.4.1 Server settings (my.cnf)
```ini
[mysqld]
innodb_ft_min_token_size = 2          # default 3 drops 2-letter Persian words such as «مو» (hair), «لب» (lip), «تب» (fever)
innodb_ft_enable_stopword = ON
innodb_ft_server_stopword_table = 'pharmacy/search_stopwords'   # custom Persian list
ngram_token_size = 2                  # only if the ngram parser is used (not recommended for Persian)
```
After changing token size, rebuild the FULLTEXT index (`ALTER TABLE products DROP INDEX ..., ADD FULLTEXT ...` or `OPTIMIZE TABLE` with `innodb_optimize_fulltext_only=ON`). **[CONFIRM]** on the target MySQL version.

Persian stopwords table (`search_stopwords(value VARCHAR(30))`): از، به، با، در، برای، و، یا، که، این، آن، را، تا، بر.

#### D21.4.2 Denormalised search column
Add to `products`:
```php
$table->text('search_text')->nullable();               // normalised: name_fa, name_en, brand fa/en, tags, ingredients keywords, synonyms
$table->string('search_codes', 500)->nullable();       // SKUs and barcodes of all variants, space separated
$table->fullText('search_text');
$table->index('search_codes');                         // for prefix LIKE 'abc%'
```
`search_text` is rebuilt by `Search\Actions\BuildProductSearchText` on product/variant/brand/tag save (queued).

#### D21.4.3 Scout configuration
```bash
composer require laravel/scout
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```
`.env`
```dotenv
SCOUT_DRIVER=database
SCOUT_QUEUE=true
```
Model:
```php
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Attributes\SearchUsingPrefix;
use Laravel\Scout\Searchable;

final class Product extends Model
{
    use Searchable;

    #[SearchUsingFullText(['search_text'])]
    #[SearchUsingPrefix(['search_codes'])]
    public function toSearchableArray(): array
    {
        return [
            'search_text'  => $this->search_text,
            'search_codes' => $this->search_codes,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === ProductStatus::Active;
    }
}
```

#### D21.4.4 Query pipeline

1. Normalise the query (D21.3).
2. Expand synonyms from `search_synonyms` (e.g. query «ویتامین دی» → `ویتامین دی | vitamin d | d3`).
3. Run `Product::search($q)->query(fn ($b) => $b->with([...])->where(...filters))->paginate(24)`.
4. For autocomplete use boolean prefix mode on the last word (`+ویتامین +د*`) with limit 6, cached 10 min per normalised prefix.
5. Facet counts: one SQL query per facet group over the matched ids, cached 5 min per query hash (Redis tag `search`).
6. Log to `search_logs` (normalised query, results count, clicked product).

#### D21.4.5 Ranking in MVP
Relevance from MATCH score, then boost:
```
score = match_score × 1.0
      + (is_in_stock ? 0.5 : 0)
      + log10(1 + sales_30d) × 0.3
      + (brand exact match ? 1.0 : 0)
```
Implemented as an `orderByRaw` after fetching candidate ids (top 200), so the boost stays simple and testable.

#### D21.4.6 Limits to watch

- No typo tolerance: compensate with synonyms and the "did you mean" list built from `search_logs` zero-result queries reviewed in admin.
- Facet counts get slower beyond ~5,000 products; Redis cache hides it until Phase 2.

---

### D21.5 Phase 2: Meilisearch

#### D21.5.1 Why Meilisearch
Open source and free to self-host; built-in typo tolerance, prefix search, filters with facet counts, synonyms, ranking rules; official Laravel Scout driver; **hybrid search** (keyword + vector) with a `semanticRatio` parameter (Meilisearch docs: "the default (`0.5`) works well for most use cases").

#### D21.5.2 Server sizing and install

| Products | RAM | Disk | Where |
|---|---|---|---|
| ≤ 20,000 | 2 GB (4 GB with local embedding model) | 10 GB SSD | Separate small VPS inside Iran (recommended) or app server |
| 20,000–200,000 | 4–8 GB | 20–40 GB SSD | Separate VPS |

Install (binary + systemd, or Docker). Pin a version and upgrade deliberately. **[CONFIRM]** latest stable version at install time.
```bash
# Docker example
docker run -d --name meilisearch --restart unless-stopped \
  -p 127.0.0.1:7700:7700 \
  -e MEILI_ENV=production \
  -e MEILI_MASTER_KEY="<64-char random secret>" \
  -v /srv/meili_data:/meili_data \
  getmeili/meilisearch:<pinned-version>
```
Expose only to the app server (bind 127.0.0.1 or private network + firewall). Never expose the master key to the browser.

#### D21.5.3 Laravel setup
```bash
composer require meilisearch/meilisearch-php http-interop/http-factory-guzzle
```
`.env`
```dotenv
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://10.0.0.5:7700
MEILISEARCH_KEY=<admin API key, not the master key>
SCOUT_QUEUE=true
```
Create keys with the master key once: an **admin key** for the app (indexing + search, server side only) and, if the browser ever queries Meilisearch directly, a **search-only key** restricted to the `products` index (not needed: all search goes through Laravel).

#### D21.5.4 Document shape (`toSearchableArray` for Meilisearch)
```php
return [
    'id'             => $this->id,
    'name_fa'        => PersianNormalizer::forIndex($this->name_fa),
    'name_en'        => mb_strtolower((string) $this->name_en),
    'brand_fa'       => $this->brand?->name_fa,
    'brand_en'       => $this->brand?->name_en,
    'category_ids'   => $this->categories->pluck('id')->all(),
    'category_names' => $this->categories->pluck('name_fa')->all(),
    'tags'           => $this->tags->pluck('name_fa')->all(),
    'form'           => $this->attributeValue('form'),           // e.g. "قرص"
    'suitable_for'   => $this->attributeValues('suitable_for'),  // e.g. ["بانوان", "بالای ۱۸ سال"]
    'ingredients'    => $this->ingredientKeywords(),             // short list, not full text
    'codes'          => $this->variants->flatMap(fn ($v) => [$v->sku, $v->barcode])->filter()->values()->all(),
    'min_price'      => $this->minActivePrice(),                 // Rial
    'discount_pct'   => $this->maxDiscountPercent(),
    'in_stock'       => $this->hasAvailableVariant(),
    'is_near_expiry' => $this->hasNearExpiryVariant(),
    'is_damaged_box' => $this->hasDamagedBoxVariant(),
    'product_type'   => $this->product_type->value,
    'sales_30d'      => $this->sales_30d,
    'created_at'     => $this->created_at->timestamp,
];
```

#### D21.5.5 Index settings (`config/scout.php`)
```php
'meilisearch' => [
    'host' => env('MEILISEARCH_HOST'),
    'key'  => env('MEILISEARCH_KEY'),
    'index-settings' => [
        Product::class => [
            'searchableAttributes' => ['name_fa', 'name_en', 'brand_fa', 'brand_en', 'tags', 'ingredients', 'category_names', 'codes'],
            'filterableAttributes' => ['category_ids', 'brand_fa', 'form', 'suitable_for', 'min_price', 'discount_pct', 'in_stock', 'is_near_expiry', 'is_damaged_box', 'product_type'],
            'sortableAttributes'   => ['min_price', 'discount_pct', 'sales_30d', 'created_at'],
            'rankingRules'         => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness', 'in_stock:desc', 'sales_30d:desc'],
            'typoTolerance'        => ['minWordSizeForTypos' => ['oneTypo' => 4, 'twoTypos' => 8], 'disableOnAttributes' => ['codes']],
            'pagination'           => ['maxTotalHits' => 2000],
            'faceting'             => ['maxValuesPerFacet' => 100],
        ],
    ],
],
```
Apply with `php artisan scout:sync-index-settings`, then `php artisan scout:import "App\Modules\Catalog\Models\Product"`.
**[CONFIRM]** custom ranking rule syntax (`attribute:desc`) and that `in_stock` as a boolean sorts as expected on the installed version.

#### D21.5.6 Synonyms
Synced from the admin `search_synonyms` table to Meilisearch with a job (`PUT /indexes/products/settings/synonyms`), mutual groups:
```json
{
  "ویتامین دی": ["ویتامین d", "d3", "vitamin d"],
  "امگا 3": ["omega 3", "روغن ماهی", "امگا۳"],
  "ضد آفتاب": ["ضدآفتاب", "sunscreen", "کرم ضد آفتاب"]
}
```

#### D21.5.7 Hybrid (semantic) search
Goal: queries like «یه چیزی برای ریزش مو» (something for hair loss) find biotin, zinc and hair-supplement products even when those words are not in the title.

**Embedding model (local, free, multilingual with Persian):**

| Option | How | Notes |
|---|---|---|
| A. Ollama + `bge-m3` | Run Ollama on the search VPS; Meilisearch embedder `source: "ollama"` | Strong multilingual quality; 1024 dimensions; CPU works for small catalogs, slower indexing **[CONFIRM]** speed |
| B. Meilisearch built-in `huggingFace` embedder with a multilingual sentence-transformers model (e.g. `paraphrase-multilingual-MiniLM-L12-v2`, 384 dims) | Runs inside Meilisearch, no extra service | Lighter; check model compatibility with Meilisearch's HuggingFace embedder **[CONFIRM]** |
| C. `userProvided` | Our Laravel job computes vectors (via Ollama) and sends them in `_vectors` | Full control, reusable for "similar products" |

Foreign paid APIs (OpenAI, Cohere…) are not recommended: access and payment from Iran are unreliable.

Embedder settings (option A):
```json
PATCH /indexes/products/settings
{
  "embedders": {
    "default": {
      "source": "ollama",
      "url": "http://127.0.0.1:11434/api/embeddings",
      "model": "bge-m3",
      "documentTemplate": "{{doc.name_fa}} {{doc.name_en}} برند {{doc.brand_fa}}. دسته: {{doc.category_names}}. کاربرد: {{doc.tags}}. ترکیبات: {{doc.ingredients}}"
    }
  }
}
```
Keep `documentTemplate` short and meaningful (name, brand, category, uses, key ingredients); long descriptions add noise and slow indexing.

Query with hybrid search from Laravel:
```php
$results = Product::search($q, function (Indexes $index, string $query, array $options) use ($filters) {
    $options['filter'] = $filters;                       // e.g. ['in_stock = true', 'min_price 0 TO 50000000']
    $options['facets'] = ['brand_fa', 'form', 'suitable_for', 'category_ids'];
    $options['hybrid'] = ['embedder' => 'default', 'semanticRatio' => 0.4];
    return $index->search($query, $options);
})->paginate(24);
```
Tuning: start `semanticRatio` at 0.4 (keyword-heavy, better for exact product/brand names); use 0.7 on the zero-result fallback ("did you mean these?"). For SKU/barcode-looking queries (digits only) skip hybrid.

#### D21.5.8 Indexing pipeline

- `Searchable` + `SCOUT_QUEUE=true`: model changes are pushed by queued jobs on the `search` queue (Horizon supervisor with 1–2 processes).
- Related changes (brand rename, tag rename, price recalculation, stock status) dispatch `ReindexProducts` for affected product ids in chunks of 500.
- Nightly `scout:import` safety re-sync (off-peak) and a weekly full re-index into a new index + swap (`swap-indexes`) to avoid downtime **[CONFIRM]** command availability in the Scout version.

#### D21.5.9 Operations

- **Backups:** daily Meilisearch dump (`POST /dumps`) or snapshot; the index can always be rebuilt from MySQL (source of truth), so backups only shorten recovery time.
- **Monitoring:** `/health` in spatie/laravel-health; alert if down > 2 min; search falls back to MySQL FULLTEXT automatically (keep the Phase 1 code path as fallback driver).
- **Security:** private network only, master key stored only on the search server, admin key in Laravel `.env`, firewall allows only the app server IP, TLS if crossing networks.
- **Upgrades:** dump → upgrade binary/container → import dump; test on staging first.

---

### D21.6 Similar products (both phases)

Shown on the product page ("محصولات مشابه") and in the cart ("ممکن است نیاز داشته باشید").

1. **Nightly job** `ComputeProductSimilarities`:
   - Phase 1 (no vectors): similarity by shared category, shared tags, same form, same suitable-for, similar price band, co-purchase count from orders (weights tunable in config).
   - Phase 2 (vectors): get each product's embedding (Ollama `bge-m3` or Meilisearch `_vectors`), compute cosine similarity in PHP in batches, combine with the Phase 1 score.
2. Store the top 12 per product:
```php
Schema::create('product_similarities', function (Blueprint $table) {
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('similar_product_id')->constrained('products')->cascadeOnDelete();
    $table->decimal('score', 6, 4);
    $table->string('source', 16);        // rules | vector | copurchase | manual
    $table->timestamps();
    $table->primary(['product_id', 'similar_product_id']);
    $table->index(['product_id', 'score']);
});
```

3. Admin can pin or exclude items (`product_relations` with type `alternative` overrides the computed list).
4. PDP reads the table (cached), so there is no runtime vector search.

Embeddings, if stored in MySQL for this batch: `product_embeddings(product_id PK, model, dims, vector BLOB (packed float32), updated_at)`; pack/unpack with `pack('g*', ...$floats)` / `unpack('g*', $blob)`.

---

### D21.7 Admin features

- **Synonyms** resource (Filament): term + list of synonyms, sync button for Meilisearch.
- **Search insights** page: top queries, zero-result queries (with "add synonym" action), queries with low click-through.
- **Boosts / pins** (Phase 2): pin products for a query (e.g. «ضد آفتاب» → featured brand) implemented as a pre-query lookup that prepends pinned ids.
- **Reindex** action (super admin): re-sync index settings and re-import.

---

### D21.8 Testing

| Test | What |
|---|---|
| Unit | `PersianNormalizer` cases (letters, digits, ZWNJ, units); synonym expansion; ranking formula |
| Feature (MySQL) | search finds products by Persian name, English name, brand, SKU prefix; inactive products excluded; filters and facet counts correct; zero-result logging |
| Feature (Meilisearch) | run against a Meilisearch test container in CI (or skip with a group tag locally); index settings applied; typo («ویتامیم» → «ویتامین»); hybrid query returns hair supplements for «ریزش مو» with a fixture dataset |
| Fallback | Meilisearch down → MySQL driver used, page still returns results |
| Performance | autocomplete p95 < 150 ms on 5,000-product fixture; PLP search p95 < 400 ms |

---

### D21.9 Implementation steps

| Step | Phase | Work | Done when |
|---|---|---|---|
| SR1 | 1 | `PersianNormalizer`, stopwords table, `search_text`/`search_codes` columns, `BuildProductSearchText` job | Unit tests pass |
| SR2 | 1 | Scout `database` driver, search page, autocomplete, synonyms table, search logs | Feature tests pass; autocomplete < 150 ms |
| SR3 | 1 | Facets with cached counts, ranking boost | Correct counts in tests |
| SR4 | 1 | Similar products (rules + co-purchase) nightly job + PDP rail | Table filled; PDP shows 12 items |
| SR5 | 1 | Admin: synonyms, search insights | Admin can add synonym from zero-result list |
| SR6 | 2 | Meilisearch server (staging then prod), keys, firewall, health check | `/health` green; app connects |
| SR7 | 2 | Switch Scout driver, document shape, index settings, synonyms sync, fallback to MySQL | All search tests pass on both drivers |
| SR8 | 2 | Ollama + `bge-m3` (or HuggingFace embedder), embedder settings, hybrid query, ratio tuning | Semantic fixture tests pass; manual check with 30 real queries |
| SR9 | 2 | Vector-based similar products, pins/boosts | PDP similar list improved in review |

Phase 1 steps SR1–SR5 are delivered inside **S12** (SR4 similar products may follow S13); Phase 2 steps SR6–SR9 run after launch. Each step follows the working agreement (E1) (plan with examples → approval → implement → tests → ask to commit → ask to push), using the `implement-roadmap-section` skill.

---

### D21.10 Costs

| Item | MVP | Phase 2 |
|---|---|---|
| Software licences | 0 | 0 (open source) |
| Server | none extra | 1 VPS 4 vCPU / 4–8 GB RAM inside Iran (search + Ollama), or app server upgrade |
| Embedding API | none | none (local model) |
| Operations | none | backups, monitoring, upgrades (~1–2 h/month) |

## D22. Reporting and the reporting warehouse

> **Decision:** dashboard reports read from **pre-aggregated, denormalised warehouse tables** (`rpt_*`) in a separate `reporting` database, refreshed **every 12 hours** (02:00 and 14:00 Asia/Tehran). Operational work queues (R8, R10, R26, R27) stay **live**. ★ = MVP report; others come after launch when data accumulates (their tables can still be built in MVP).

### D22.1 Reports list

**Sales and profit**

| ID | Report | What it shows | Source | ★ |
|---|---|---|---|---|
| R1 | Sales overview | Revenue, orders, average order value for today/7/30/90 days vs previous period | `rpt_daily_sales` | ★ |
| R2 | Gross & net margin | Revenue − provider cost; then − shipping, payment fees, packaging, coupons; daily trend | `rpt_daily_sales` | ★ |
| R3 | Margin by category & brand | Best and worst earning categories/brands (to tune markup rules) | `rpt_daily_category_brand` | ★ |
| R4 | Top products | Best sellers by quantity, revenue and margin | `rpt_product_performance` | ★ |
| R5 | Low-margin / loss orders | Orders where shipping or discounts ate the profit | `rpt_order_profitability` | ★ |
| R6 | Sales by city & province | Where customers are | `rpt_daily_geo` | |
| R7 | Sales by hour & weekday | Heatmap for timing deals and SMS | `rpt_sales_heatmap` | |

**Orders and operations**

| ID | Report | What it shows | Source | ★ |
|---|---|---|---|---|
| R8 | Orders by status (work queue) | Counts per status now + oldest order per status | **live** | ★ |
| R9 | Fulfilment speed | Paid → sourced → packed → handed to Tipax → delivered times vs 24 h target | `rpt_fulfilment` | ★ |
| R10 | Sourcing backlog | Items waiting on providers, by provider, oldest request | **live** | ★ |
| R11 | Cancellations & returns | Rates, reasons, refunded amounts | `rpt_daily_cancellations_returns` | ★ |
| R12 | Out-of-stock demand | Stock-alert sign-ups and blocked add-to-cart attempts by product | `rpt_stock_demand` | |

**Providers and pricing**

| ID | Report | What it shows | Source | ★ |
|---|---|---|---|---|
| R13 | Provider performance | Purchase value, average discount, fill rate, lead time per provider | `rpt_provider_performance` | ★ |
| R14 | Price change log | Cost/price changes above X % after imports | `rpt_price_changes` | ★ |
| R15 | Expiry watch | Variants expiring in 30/60/90 days and their value | `rpt_expiry_watch` | |

**Shipping and payments**

| ID | Report | What it shows | Source | ★ |
|---|---|---|---|---|
| R16 | Shipping cost vs charged | Customer-paid shipping vs Tipax actual cost, by service and province | `rpt_daily_shipping` | ★ |
| R17 | Delivery performance | On-time rate, average days per carrier service / own courier | `rpt_daily_shipping` | |
| R18 | Payment success rate | Started vs verified Zarinpal payments, failures by error code, fees | `rpt_daily_payments` | ★ |

**Customers and marketing**

| ID | Report | What it shows | Source | ★ |
|---|---|---|---|---|
| R19 | New vs returning customers | Sign-ups, first orders, repeat rate in 30/60 days | `rpt_daily_sales`, `rpt_customer_summary` | ★ |
| R20 | Customer lifetime value | Top customers, average spend per customer, segments | `rpt_customer_summary` | |
| R21 | Coupon performance | Uses, discount given, revenue and margin per coupon | `rpt_coupon_performance` | ★ |
| R22 | Abandoned carts | Carts with items and no order in 24 h, value, top products | `rpt_daily_abandoned_carts` | |
| R23 | Conversion funnel | Visits → product views → add to cart → checkout → paid | `rpt_daily_funnel` (needs `analytics_events`) | |

**Search and catalog**

| ID | Report | What it shows | Source | ★ |
|---|---|---|---|---|
| R24 | Search insights | Top searches, zero-result searches, searches with no click | `rpt_daily_search` | ★ |
| R25 | Catalog health | Products missing photo/weight/dimensions/IRC/description; never-sold products | `rpt_catalog_health` | ★ |

**System health**

| ID | Report | What it shows | Source | ★ |
|---|---|---|---|---|
| R26 | Integration errors | Failed Tipax, Zarinpal, Melli Payamak calls in last 24 h | **live** (`integration_logs`, `notification_logs`) | ★ |
| R27 | Queue & job health | Waiting/failed jobs, wait times (Horizon) | **live** | |

### D22.2 Architecture
- **Connection:** `config/database.php` → connection `reporting` (same MySQL server, database `pharmacy_reporting`, user with write access only to that DB and **read-only** access to the main DB). Warehouse tables never join back to the shop DB at dashboard time.
- **Module:** `app/Modules/Reporting` (Builders, Queries, Filament widgets/pages, Jobs, Migrations on the `reporting` connection).
- **Refresh schedule:** `Schedule::job(new RefreshReportingWarehouse)->twiceDaily(2, 14)->timezone('Asia/Tehran')->withoutOverlapping()->onOneServer();` on Horizon queue `reporting` (1 process, timeout 1800 s, memory 1 GB).
- **Incremental window:** every run recomputes the **last 7 days** (orders change: cancellations, returns, actual Tipax cost) for date-based tables; snapshot tables (`rpt_customer_summary`, `rpt_expiry_watch`, `rpt_catalog_health`, `rpt_product_performance`) are rebuilt fully.
- **Weekly full rebuild** (Friday 03:00): build into `*_new` tables, then atomic `RENAME TABLE rpt_x TO rpt_x_old, rpt_x_new TO rpt_x; DROP TABLE rpt_x_old;`.
- **Idempotency:** incremental = `DELETE WHERE date BETWEEN window` + `INSERT … SELECT` inside one transaction per table; re-running gives identical results.
- **Run log:** `rpt_refresh_runs` (id, started_at, finished_at, status, window_from, window_to, tables JSON with rows/duration, error). Dashboard shows "آخرین به‌روزرسانی: …" and a **Refresh now** action (super admin, queued, rate-limited to once per 15 min).
- **Alert:** health check fails if the last successful run is older than 13 hours.
- **Dates:** every date-grained row stores `date` (Tehran calendar date), `jalali_year`, `jalali_month`, `jalali_week`, `jalali_day` so charts group by Persian month/week.
- **Money:** all amounts `unsignedBigInteger` Rial (signed `bigInteger` for margins/differences that can be negative).
- **Cost allocation:** order-level shipping (actual cost), shipping charged, payment fee, packaging cost and coupon discount are allocated to order items **proportionally to item revenue**; rounding remainder goes to the largest item so totals match exactly.
- **Margin definitions:**
  - `gross_margin = revenue − provider_cost`
  - `net_margin = gross_margin − (shipping_cost_actual − shipping_charged) − payment_fee − packaging_cost` (coupon discount is already deducted from revenue)
- **Orders included:** `paid_at` not null; cancelled/refunded orders are included with negative refund rows so totals reconcile with payments.

### D22.3 Warehouse tables

`rpt_fact_order_items` (grain: one order item; base fact for R1–R7)
```
id, order_id, order_item_id (unique), order_number, customer_id, is_first_order (bool)
date, hour (0–23), weekday (0–6, Saturday=0), jalali_year, jalali_month, jalali_week, jalali_day
order_status, province_id, province_name, city_id, city_name
product_id, product_name, variant_id, sku, brand_id, brand_name, category_id, category_name, product_type
provider_id, provider_name, carrier_service_code, coupon_code
qty, revenue, provider_cost, discount_alloc, shipping_charged_alloc, shipping_cost_alloc, payment_fee_alloc, packaging_alloc
gross_margin, net_margin, refunded_amount, refreshed_at
indexes: (date), (date, category_id), (date, brand_id), (product_id, date), (customer_id), (province_id, date)
```

`rpt_daily_sales` (grain: day) → R1, R2, R19
```
date (PK), jalali_*, orders, items, revenue, aov, provider_cost, gross_margin, net_margin,
shipping_charged, shipping_cost, payment_fees, packaging_cost, discounts,
new_customers, returning_customers, first_orders, cancellations, refunds_amount, refreshed_at
```

`rpt_daily_category_brand` (day × category × brand) → R3: `date, category_id, category_name, brand_id, brand_name, qty, revenue, provider_cost, gross_margin, net_margin` · unique(date, category_id, brand_id)

`rpt_product_performance` (product × period) → R4: `period ('7d','30d','90d'), product_id, product_name, brand_name, qty, revenue, gross_margin, net_margin, rank_qty, rank_revenue, rank_margin` · unique(period, product_id)

`rpt_order_profitability` (order) → R5: `order_id (PK), order_number, date, revenue, provider_cost, shipping_charged, shipping_cost, payment_fee, packaging_cost, discount, net_margin, net_margin_pct, is_loss (bool), loss_reason ('shipping','discount','both')`

`rpt_daily_geo` (day × city) → R6: `date, province_id, province_name, city_id, city_name, orders, customers, revenue, net_margin`

`rpt_sales_heatmap` (weekday × hour, rolling 90 days) → R7: `weekday, hour, orders, revenue, window_from, window_to`

`rpt_fulfilment` (order) → R9: `order_id (PK), date, paid_at, sourced_at, packed_at, shipped_at, delivered_at, paid_to_sourced_min, sourced_to_packed_min, packed_to_shipped_min, shipped_to_delivered_min, paid_to_shipped_min, met_24h_sla (bool), shipping_method`

`rpt_daily_cancellations_returns` (day × type × reason) → R11: `date, type ('cancel','return'), reason, count, refunded_amount`

`rpt_stock_demand` (day × product) → R12: `date, product_id, variant_id, stock_alert_signups, blocked_add_to_cart` (blocked attempts recorded in `analytics_events`)

`rpt_provider_performance` (provider × Jalali month) → R13: `jalali_year, jalali_month, provider_id, provider_name, purchases, purchase_value, avg_discount_pct, items_requested, items_received, fill_rate_pct, avg_lead_hours`

`rpt_price_changes` (variant × change) → R14: `changed_at, date, variant_id, sku, product_name, old_cost, new_cost, old_price, new_price, cost_change_pct, price_change_pct, price_import_id, flagged (bool)`

`rpt_expiry_watch` (variant, snapshot) → R15: `variant_id, sku, product_name, expiry_date, days_left, bucket ('≤30','31–60','61–90'), is_near_expiry, last_cost, last_price`

`rpt_daily_shipping` (day × carrier service × province) → R16, R17: `date, carrier_code, service_code, province_id, shipments, shipping_charged, shipping_cost, difference, delivered, delivered_on_time, avg_delivery_days`

`rpt_daily_payments` (day × gateway) → R18: `date, gateway, started, verified, failed, cancelled, failures_by_code (JSON), verified_amount, fees`

`rpt_customer_summary` (customer, snapshot) → R19, R20: `customer_id (PK), first_order_at, last_order_at, orders, revenue, net_margin, avg_order_value, days_since_last_order, segment ('new','active','at_risk','lost','vip'), city_name`

`rpt_coupon_performance` (coupon × day) → R21: `date, coupon_id, code, uses, unique_customers, discount_total, revenue, net_margin`

`rpt_daily_abandoned_carts` (day) → R22: `date, carts, carts_value, top_products (JSON top 10)`

`rpt_daily_funnel` (day) → R23: `date, sessions, product_views, add_to_cart, checkout_started, orders_paid` (needs a small `analytics_events` table: session_id, customer_id, event, product_id, created_at; written asynchronously, kept 90 days)

`rpt_daily_search` (day × normalised query) → R24: `date, normalized_query, searches, zero_results, clicks, ctr_pct`

`rpt_catalog_health` (product, snapshot) → R25: `product_id, product_name, status, missing_image, missing_weight, missing_dimensions, missing_irc, missing_description, never_sold, issues_count`

`rpt_refresh_runs`: see D22.2.

### D22.4 Laravel design
```
app/Modules/Reporting/
  Contracts/WarehouseBuilder.php        // table(): string; build(ReportWindowData $window): BuildResultData
  Data/ReportWindowData.php             // readonly: from (date), to (date), full (bool)
  Data/BuildResultData.php              // readonly: table, rows, durationMs
  Builders/FactOrderItemsBuilder.php    // runs first; others aggregate from it where possible
  Builders/DailySalesBuilder.php … (one per rpt_ table)
  Actions/RefreshWarehouse.php          // runs builders in dependency order, writes rpt_refresh_runs
  Jobs/RefreshReportingWarehouse.php    // queue "reporting"
  Queries/SalesOverviewQuery.php …      // read models for widgets (return DTOs)
  Filament/Widgets/…                    // StatsOverview (R1/R2), charts, tables
  Filament/Pages/Reports/*.php          // one page per report group with filters (date range, Jalali month, category, brand, provider)
  Database/Migrations/                  // on the "reporting" connection
```
- Builders use `INSERT … SELECT` SQL on the database server (fast, low memory), not Eloquent loops.
- Widgets read only through Queries; Queries return DTOs; Filament never queries `rpt_*` tables directly.
- Dashboard (B12): 4 KPI stats (R1/R2), sales & margin chart (30 days), work queues (R8, R10 live), integration errors (R26 live), last-refresh time.
- Export: each report page offers CSV/XLSX export of the current filter (OpenSpout).
- Access: report pages visible to `super_admin`; operators see only R8, R9, R10, R16; margins and provider costs never visible to `content_editor`.

### D22.5 Tests
- Each builder: create orders/items/payments/shipments with factories → run builder for a window → assert totals (revenue, margins, allocations sum exactly to order totals).
- Idempotency: run twice → same rows and sums.
- Window: data outside the 7-day window is untouched by incremental runs.
- Swap: weekly full rebuild leaves the dashboard readable during the build (table exists at all times).
- Health: last successful run older than 13 h → health check fails.
- Widgets/pages: render with seeded `rpt_*` data; access restricted by role.

---

# Part E: Implementation steps and the AI working agreement

## E1. Working agreement (mandatory for every AI agent)

For **every** step below, the agent follows this loop and never skips a gate:

1. **Read**: the step in E3 and every section it references (Part B/C/D). **UI steps** (storefront or admin screens) must also read **B2, B3, B7–B13** and pass the **B13 design QA checklist** before asking to commit.
2. **Clarify before coding**: send the user a short plan with **concrete examples** and wait for an explicit "yes" or corrections. The plan contains:
   - what will be created (files, tables, routes, Filament resources, Livewire components);
   - an **example** of the data or UI (e.g. a sample row, a sample request/response, a sketch of the screen);
   - open decisions as questions with a recommended default;
   - packages to install (name + version), if any.
3. **Branch**: `git checkout -b feat/S{nn}-{slug}` from `main`.
4. **Implement** following Part C rules (module structure, **Form Request → DTO → Action → API Resource** per C5, enums, strict types).
5. **Test**: write Pest tests listed in the step (and any needed), then run:
   `vendor/bin/pint --test && vendor/bin/phpstan analyse && php artisan test --parallel`.
6. **Report**: show the user the summary of changes, test results (counts, failures = 0), and screenshots/URLs for UI steps. Fix failures before reporting.
7. **Ask to commit**: "Tests pass. Shall I commit with message `feat(module): …`?" Commit only after "yes".
8. **Ask to push**: "Shall I push `feat/S{nn}-…` to `origin` (and open a PR)?" Push only after "yes".
9. Mark the step done in `ROADMAP_PROGRESS.md` (step id, date, commit hash).

Never: commit or push without explicit approval; call real Tipax/Zarinpal production APIs; store secrets in the repo; change a previous step's behaviour without saying so.

### Example of step 2 (clarification message)
> **S07 Brands, plan for your confirmation**
> I will create `brands` table, `Brand` model, `BrandResource` in Filament (list with logo, name, product count, active toggle; form with name_fa, name_en, slug auto from name_en, logo upload, country, description, SEO) and storefront pages `/b` and `/b/{slug}`.
> Example row: `{ name_fa: "یوروویتال", name_en: "Eurho Vital", slug: "eurho-vital", country: "Germany", is_active: true }`
> Questions: 1) Slug from English name (recommended) or Persian? 2) Show brands with 0 active products on `/b`? (recommended: no)
> No new packages. Tests: model factory, Filament list/create/edit, slug uniqueness, storefront brand page shows only active products.
> Reply "yes" or tell me what to change.

## E2. Step map

| Phase | Steps |
|---|---|
| 0 Foundation | S00–S05 |
| 1 Catalog & media | S06–S13 |
| 2 Pricing & providers | S14–S16 |
| 3 Customers & cart | S17–S20 |
| 4 Checkout & shipping (stand-ins for external services) | S21, S24, S25 |
| 5 Orders operations | S29–S32 |
| 6 Content, SEO, trust | S33–S36 |
| 7 Hardening & reporting | S37–S39, S41–S42 |
| 8 External integrations, then launch | S22, S23, S26, S27, S28 (Tipax, Zarinpal, SMS; owner 2026-11-09), then S40 |

## E3. Steps

Each step lists: **Refs** (sections to read), **Build**, **Tests**, **Done when**.

### S00 Project bootstrap
- **Refs:** C1, C2, C4, D19.1
- **Build:** new Laravel project (13.x if Filament 5 + key packages support it, else 12.x), PHP 8.5, MySQL, Redis, `.env.example` (D11.1), Pint, Larastan, Rector, Pest 4, GitHub/GitLab CI, `ROADMAP_PROGRESS.md`, README.
- **Tests:** Pest arch preset (`strict types`, no `dd/dump`, no `env()` outside config); CI green.
- **Done when:** `php artisan test` green in CI; versions confirmed with user.

### S01 Modular skeleton
- **Refs:** C3
- **Build:** `app/Modules/*` folders, `ModuleServiceProvider` base (routes, migrations, views, Livewire, policies), registration list in `bootstrap/providers.php`, arch tests for module boundaries, `make:module` artisan command (optional).
- **Tests:** arch tests; a dummy module route returns 200.

### S02 Front-end foundation (TALL + TallStackUI)
- **Refs:** B2, B3, B4, D14.5, B7–B13
- **Build:** Tailwind 4 tokens, Vazirmatn self-hosted, RTL layout, TallStackUI v4 install + `customize()` to palette, base layout (header, search slot, bottom nav, footer), `ui.price`, `@toman`, Persian digits helper, Jalali helper.
- **Tests:** layout renders `dir="rtl"`; helpers unit tests (۱٬۲۵۰٬۰۰۰ formatting, digit conversion).

### S03 Filament admin panel
- **Refs:** B4, B6, D16 (admin)
- **Build:** Filament 5 panel `/admin`, `admin` guard + `admin_users` table, MFA (TOTP) required, Persian locale + RTL, brand colour/font, navigation groups (B6), dashboard placeholder.
- **Tests:** guest redirected; admin without MFA forced to enrol; customer cannot access `/admin`.

### S04 Roles and permissions
- **Refs:** D3.6 (admin), D16
- **Build:** spatie/laravel-permission, roles `super_admin`, `operator`, `content_editor`; policies scaffold; Role management with **Filament Shield** (`bezhansalleh/filament-shield`: role resource, permission generation for every resource/page/widget, `super_admin` role); activitylog.
- **Tests:** each role sees only allowed resources.

### S05 Locations
- **Refs:** D4, D11.5
- **Build:** provinces/counties/cities/regions/neighbourhoods tables + models, `locations:import` from sajaddp JSON, optional Tehran neighbourhoods, Filament read/edit (enable delivery cities).
- **Tests:** import command with fixture creates expected counts; city belongs to province.

### S06 Shared kernel
- **Refs:** C4, D12.1
- **Build:** `Money` (Rial) VO, `PersianNormalizer` (ی/ي, ک/ك, digits, ZWNJ), Persian-safe slugger, base enums trait (`label()`), `integration_logs` + redacting HTTP client macro.
- **Tests:** unit tests for each.

### S07 Brands
- **Refs:** D3.1, B5 (`/b`), D15.3, D14.3 (brand logo), B7–B13
- **Build:** migration, model, factory, BrandResource, storefront `/b` and `/b/{slug}` (PLP shell).
- **Tests:** Filament CRUD, slug unique, only active brands on `/b`.

### S08 Categories
- **Refs:** D3.1, B5, D15.3, B7–B13
- **Build:** tree with parent_id, reorderable tree in Filament, mega-menu component (cached), `/c/{slug}` shell, breadcrumbs.
- **Tests:** tree ordering, menu cache invalidation, breadcrumb.

### S09 Media pipeline
- **Refs:** D8, D14 (all)
- **Build:** medialibrary + presets config, conversions (AVIF/WebP/JPEG per slot), ThumbHash + dominant colour, `media.picture` component, private originals, CDN disk config, Filament upload fields with validation.
- **Tests:** upload validation (min size, MIME), conversions queued, picture component outputs correct `srcset`/`sizes`.

### S10 Products and variants
- **Refs:** D3.1, D12.2, D12.3, D15.3
- **Decisions (2026-11-07):** only Rx is blocked (OTC sellable); slugs from the English name, else Persian; admins type prices in toman until the pricing engine (S15), which then sets `price`; activation needs a brand, primary category, an active variant with a price and at least one photo; medicines need an official price and are never priced above it.
- **Build:** products, product_variants, categories pivot, FAQs, relations; ProductResource with tabs and Variants relation manager; dimension/weight fields + `dimensions_verified`; `requires_prescription` guard; `Brand::products()` and `Brand::hasProducts()` checking it (a brand with products cannot be deleted, decided 2026-11-07 in S07).
- **Tests:** create product with 2 variants; rx product cannot be activated for sale in MVP; deleting a brand that has products is refused; factories.

### S11 Attributes and tags
- **Refs:** D3.1, B1 (Darunet filters, Darochi nutrient chips)
- **Build:** attributes/values, tags (nutrient/need/general) + pivots, Filament resources, chips component.
- **Tests:** attach values/tags; chip links to `/t/{slug}`.

### S12 PLP and search
- **Decisions (2026-11-07):** default SKU per product with fallback in admin order, the SKU list with price, sale price and compare-at (box) price in the Meilisearch document, `price`/`discount_pct` filters and sorts from the default SKU, discount measured against the box price, box-price cap for every product type, search-box rows show the price (add-to-cart button in S19) — see D3.1 «Default SKU»; Meilisearch now (see D21 update) for search and listings; numbered pages + «نمایش بیشتر»; a simple `/p/{slug}` product page now (S13 builds the full page); Search module owns the box, `/search`, synonyms (+ Excel/CSV import), logs and insights, and asks Catalog through its queries; the MySQL `innodb_ft_min_token_size = 2` setting is kept in the Docker config.
- **Refs:** D21 (search engine plan, SR1–SR5), D13.2, D15.1, D17, B1, B7–B13
- **Build:** CategoryPage, BrandPage, TagPage, SearchPage with filters (brand, form, suitable-for, price, in-stock, discount %), sort, cursor pagination, mobile filter slide, quick view, instant search box, Scout database driver, synonyms, search logs.
- **Tests:** filter combinations, Persian normalisation in search, zero-result logging, query count bounded (no N+1).

### S13 PDP
- **Refs:** B5 (PDP), B1 (Darunet PDP), D14.3 (PDP), D13.11 (stock alert), B7–B13
- **Build:** ProductPage with gallery/zoom, variant picker (opens on the default SKU; **no picker when the product has one SKU**, decided 2026-11-07 in S13b; shows each SKU's own image when it has one), optional SKU image upload in the variant form + round thumbnail in the variants table, price, expiry, badges, tabs, related, sticky buy bar, stock alert, product questions (submit), reviews list (approved only).
- **Tests:** renders variant change, shows expiry, rx product shows "not available online yet", stock alert subscription.

### S13b Collections, popular products and home page builder
- **Decisions (2026-11-07):** banners are a reusable library with a picked link target (category, collection, brand, tag, product, the categories / collections / brands list pages, or an external URL) and can be placed on the home page (slider widget) and at the top of category, collection, brand, tag and list pages; collections are hand-picked product lists; «محصولات محبوب» is a system collection; the empty search box shows its first 6 in-stock products as cards (phone: swipeable row with the next card peeking; tablet/desktop: 3 × 2 grid) with «مشاهده همه» → `/collections/popular`, the cards get «+ افزودن به سبد» in S19; the admin builds the home page from widgets (slider, collection, brands, categories, tags, trust), each showing at most 6 items.
- **More decisions (2026-11-07 – 2026-11-09):** admin selects show round images (brands, categories, tags, collections); products are picked by SKU and the product is saved; every product has at least one SKU (asked on create; the last cannot be deleted); full-word addresses with 301s from the short ones; no home page HTML cache; widgets show only with at least one item, and the admin says why one is hidden; «جایگاه بنرها» removed from the admin (banners are set in home sliders only); Swiper sliders (banner slider always, other rows only when they overflow); `HomePageDemoSeeder` for sample data (`php artisan db:seed --class=HomePageDemoSeeder`, not in production).
- **Refs:** D3.1 (collections), D3.6 (home widgets, banners), B5, B9.2, D14.2 (banner presets), D21.2, B7–B13
- **Build:** banners library + banner_placements (Filament «بنرها»: images, link target picker, schedule), banner/slider at the top of category, collection, brand, tag and list pages (rendered; no admin screen sets them, see D3.6), `/categories` and `/collections` list pages; collections + collection_product, Filament «کالکشن‌ها» (product picker, drag order, schedule; system collection not deletable), `/collections/{slug}` PLP, `collection_ids` in the Meilisearch document; empty-search popular products in the search box; home_widgets + banners, Filament «چیدمان صفحه اصلی» (widget forms per type, banners tab for sliders, drag order, schedule, preview, warnings over 8 widgets), Home page rendering the widgets that have items (no HTML cache).
- **Tests:** banner link resolves for each target type and follows a slug change; banner with an inactive target or outside its schedule is hidden; ≤ 6 banners per placement; collection page lists only its products in admin order; schedule hides expired collections, widgets and banners; empty search shows ≤ 6 in-stock popular products; every widget renders ≤ 6 items; widget order; widgets without items are left out and the admin shows why; system collection cannot be deleted.

### S14 Providers and provider prices
- **Built together with S15** (owner, 2026-11-09): switching suppliers changes the selling price, so suppliers, commission, price records and the engine land on one branch (one commit per step).
- **Refs:** D3.2, A1
- **Build:** Pricing module: providers, provider_prices, Filament «تأمین‌کنندگان» (list, form, active switch, and a view page with a relation-manager table of the supplier's SKUs and their price information, owner 2026-11-09; its sales widgets come in S42); a «قیمت خرید از تأمین‌کننده» table on each SKU (provider, cost in toman, available, lead time; add / edit / remove). Availability → SKU `stock_status` (any available provider → `available`, none → `out_of_stock`; SKUs with no provider rows keep their manual status until S15) through an action that calls `ProductSearchIndex::refresh` (the default SKU may switch). Provider costs never reach the storefront or Meilisearch.
- **Tests:** cheapest available provider selection (inactive providers and unavailable rows ignored); availability switches stock and the default SKU in the search document; costs are not in the search document; permissions.

### S15 Pricing engine and price records
- **Refs:** D3.2 (engine, variant_prices), D9
- **Build:** `brands.commission_percent`, `products.commission_percent`; `variant_prices` + `product_variants.current_price_id` (migration copies today's prices into an `initial` record per SKU and drops the old price columns once everything reads from the record); `CalculateVariantPrice` action; `RecalculateVariantPrice` and `RecalculatePrices` jobs on the `pricing` queue (after supplier price, printed price, haraj, product or brand commission changes; refresh the search index); rounding up to 1,000 toman; printed-price cap with the «سود کمتر از کمیسیون» flag; haraj rule; SKU form with a read-only selling price shown live, printed and haraj inputs, and the price history; SKU save refused without a commission; flag > 15 % changes in the history; a list of SKUs that cannot be priced (no commission or no supplier price). Replaces the S12 rules (default %, «قیمت دستی»). Update the pricing guide in the SKU form.
- **Tests:** product commission over brand, brand fallback, no commission refused, cheapest supplier, rounding, printed cap + flag, haraj below price and removed when no longer below, every trigger creates one record, latest record is read, jobs run on `pricing`, initial records from existing prices, 100 % branch coverage of the engine.

### S15b Commission per supplier and preferred supplier
- **Refs:** D3.2 (commission levels, which supplier sells), D13.9
- **Built (2026-11-15):** `product_providers`, `brand_providers`, `provider_prices.commission_percent` / `is_preferred`, `variant_prices.provider_choice`; `ActiveSuppliers` (offers per supplier with commission and level, preferred → lowest selling price); SKU page columns «کمیسیون» (with level), «قیمت فروش با کمیسیون», «تأمین‌کننده انتخابی»; «تأمین‌کنندگان محصول» on the product form, «کمیسیون به تفکیک تأمین‌کننده» on the brand form; Excel columns «کمیسیون (٪)» («-» clears) and «تأمین‌کننده انتخابی»; repricing of exactly the affected SKUs on `pricing`; «راهنمای قیمت‌گذاری» page; inline guides updated.
- **Tests:** each commission level over the next, preferred SKU and product suppliers and their fallback to the lowest selling price, lowest selling price over lowest cost, brand × supplier change reprices only that brand's SKUs of that supplier, Excel columns, records keep level and choice.

### S16 Price import
- **Refs:** D13.9, D3.2
- **As built (2026-11-14):** «دانلود قالب اکسل» and «ورود قیمت از اکسل» above the suppliers table (owner, 2026-11-10); the template is filled with every current supplier price (columns: supplier id, supplier name for reference, SKU code, barcode, product name for reference, cost in toman, «موجود نزد تأمین‌کننده», «فروش از این تأمین‌کننده», lead time) plus a guide sheet; rows are matched by our SKU code, then barcode (no name matching: a wrong match would set a wrong cost); blank switch or lead-time cells keep the current value (a new row starts «بله»); rows missing from the file are left unchanged; «ورودهای قیمت» lists every import, and its page shows the preview with «اعمال» and the error report download; super admins only by default.
- **Build:** upload a supplier Excel/CSV (each row names its supplier by `provider_id`, owner 2026-11-09, so one file may cover several suppliers) → `ParsePriceImport` job (OpenSpout) on the **`price-import`** queue matches rows (barcode → SKU code → name similarity, flagged) → preview page (old cost, new cost, new selling price, change %) → apply → `ApplyPriceImport` job (same queue) updates provider_prices and availability, then the S15 engine reprices on `pricing`; error report download; its own Horizon supervisor for `price-import`.
- **Tests:** fixture file with matched/unmatched rows; preview changes nothing; apply updates provider prices and creates price records; jobs on the right queues.

### S17 Customer OTP auth
- **Refs:** D13.1, D3.3, D16, D11.6, B7–B13
- **Build:** `laravel/sanctum` (storefront customer authentication, D16), customers, otp_codes, SMS sender interface (`log` driver until S28 adds Melli Payamak), OtpLogin Livewire (TallStackUI pin), rate limits, profile completion.
- **Tests:** rate limits, expiry, attempts, login + session regeneration, guest cart merge hook.

### S18 Addresses and account area
- **Refs:** D3.3, B5 (account), B7–B13
- **Build:** addresses with map pin and Locations selects, account dashboard, profile, wishlist, my reviews; a heart button on the product card, quick view and product page adds to the wishlist (guests get the OTP login first, then the product is saved).
- **Tests:** owner-only access (policy), default address switching.

### S19 Cart
- **Refs:** D13.3, D3.4, B7–B13
- **Build:** carts/cart_items, add-to-cart action + button (on the card, quick view and the search box's product rows: a «+» that adds the default SKU, with a «تغییر» link to the product page; decided 2026-11-07, S12; S13b's empty-search popular cards and home collection rows get the same «+ افزودن به سبد»), mini-cart, CartPage, guest cookie, merge on login, free-shipping progress.
- **Tests:** qty limits, rx/cold-chain blocked, price snapshot refresh, merge.

### S20 Coupons
- **Refs:** D3.4 (coupons)
- **Build:** coupons, usages, include lists, validation action, Filament resource, apply/remove in cart.
- **Tests:** each coupon type, limits per customer/total, date window, first-order-only, concurrency (lock).

### S21 Shipping foundation
- **Refs:** D5, D5.1, D6.8
- **Build:** carriers, carrier_services, shipping_boxes, origins, shipping_rules, `ShippingCarrier` contract, DTOs, `BoxPacker`, fallback `RateTable`, Filament resources.
- **Tests:** box packing cases (single, many small, ship_separately, overweight), fallback rate.

### S24 Own courier zones
- **Refs:** D5.1 (zones, slots, couriers)
- **Build:** delivery zones (neighbourhoods/polygon), slots with capacity, couriers, `OwnCourierCarrier::quote()`.
- **Tests:** address in zone, slot capacity exhausted.

### S25 Checkout wizard
- **Refs:** D13.4, B5 (checkout), D3.4 (orders), D3.5, B7–B13
- **Build:** CheckoutWizard (address → shipping → review → pay), `PlaceOrder` action with snapshots (each item copies the supplier and cost, commission and source, printed, list, haraj and paid price of that moment, plus `variant_price_id`; D3.4), order numbering, status `pending_payment`; `PaymentGateway` contract and a `FakePaymentGateway` (local/staging only: pays at once or fails on demand) so orders can be paid end to end until Zarinpal (S26, phase 8); shipping options from the own courier (S24) and the fallback `RateTable` (S21) until Tipax quotes (S23).
- **Tests:** totals recomputed server-side, tampered prices ignored, coupon re-validated, order snapshot content.

### S29 Order admin and state machine
- **Refs:** D3.5, D15.3 (OrderResource), D13.6–D13.8, B7–B13
- **Build:** OrderResource view (timeline, items with cost/margin), guarded transitions as Filament actions (shipment status set by hand until Tipax tracking, S27 in phase 8), cancellation + refund flow, order and order item status logs (two timelines), customer OrderList/OrderDetail with tracking timeline, guest tracking.
- **Tests:** allowed/forbidden transitions, refund on cancel, customer sees only own orders.

### S30 Provider purchases (sourcing)
- **Refs:** D13.6, D3.4 (provider_purchases)
- **Build:** auto-draft purchases from paid orders grouped by provider, print/Excel export, receive quantities, missing-item handling.
- **Tests:** grouping, partial receive → order stays sourcing, full receive → ready_to_pack.

### S31 Packing
- **Refs:** D13.7
- **Build:** packing screen (suggested boxes, confirm weight), packing slip print, triggers shipment registration.
- **Tests:** packed transition requires weights; triggers job.

### S32 Returns
- **Refs:** D13.8, D6.3 (ReturnRegisterOrder)
- **Build:** return requests (customer + admin), Tipax return order, refund.
- **Tests:** 7-day window, approval flow.

### S33 Home, banners, deals
- **Refs:** B1, B5 (home, deals), D3.6 (content), D14.3 (hero), B7–B13
- **Build:** flash deals + countdown and `flash_deal` / `bundles` home widget types (the builder, banners and sliders come in S13b), deals hub (tiers, near-expiry, damaged-box), bundles page, `MarkNearExpiry` job.
- **Tests:** scheduling windows, flash deal sold-count lock, near-expiry flagging.

### S34 Static pages, FAQ, contact, menus
- **Refs:** D3.6, B5, B7–B13
- **Build:** pages, FAQ, menus (header/footer/mobile), contact form with rate limit.
- **Tests:** sanitized HTML, rate limit on contact.

### S35 Settings and trust
- **Refs:** D3.6 (settings, social, trust badges, pharmacists), B2 rule 3, B7–B13
- **Build:** spatie/laravel-settings page, social links, trust badges (e-Namad embed), pharmacist page, trust counters (live).
- **Tests:** settings cache invalidation; e-Namad embed renders.

### S36 SEO
- **Refs:** D3.6 (seo_redirects), B5
- **Build:** meta tags per page, Open Graph, JSON-LD (Product, BreadcrumbList, Organization), sitemap.xml, robots.txt, redirects middleware, canonical URLs for filters.
- **Tests:** JSON-LD present on PDP, redirect 301, sitemap includes active products only.

### S37 Security hardening
- **Refs:** D16
- **Build:** security headers/CSP, rate limiters, log redaction review, admin IP allowlist option, `composer audit` in CI, privacy export/delete.
- **Tests:** headers present, throttles return 429, redaction test.

### S38 Performance
- **Refs:** D17, D14, B2 rule 10
- **Build:** caching layer + invalidation, lazy Livewire sections, query optimisation, Lighthouse pass on Home/PLP/PDP (mobile).
- **Tests:** query-count assertions on PLP/PDP; cache invalidation tests.

### S39 Observability, backups, ops
- **Refs:** D19, D20
- **Build:** Horizon config + Supervisor file, scheduler, Sentry, Pulse, health checks + alerts, backups + restore runbook, deploy script.
- **Tests:** health check endpoints; scheduler list matches D9.

### S41 Reporting warehouse
- **Refs:** D22.2, D22.3, D22.4, D22.5, D12.1
- **Build:** `reporting` DB connection + migrations for all `rpt_*` tables and `rpt_refresh_runs`, builders (fact first, then aggregates), `RefreshWarehouse` action, `RefreshReportingWarehouse` job, twice-daily schedule + weekly full rebuild with table swap, health check (13 h), Refresh-now action, `analytics_events` table + async tracking for R12/R23.
- **Tests:** D22.5 (totals, allocation sums, idempotency, window, swap, health).

### S42 Dashboard and report pages
- **Refs:** D22.1, D22.4, B12, B7–B13
- **Build:** **SKU sales on the SKU page** (owner, 2026-11-09): widgets and charts inside each SKU's suppliers page showing how much was sold (quantity and money), how much was bought from each supplier and sold per supplier, and the SKU's total gross and net margin (from order items' `variant_price_id` records and the reporting facts; filters by date range / Jalali month); **supplier sales on the supplier view page** (owner, 2026-11-09): widgets and charts showing how many products were sold that were bought from this supplier, which products and SKUs, at which prices (selling, haraj, supplier cost from each order item's price record), how much money was sold from this supplier, and its other sales reports (gross/net margin, top SKUs, sales over time; same filters); dashboard widgets (KPIs R1/R2, sales & margin chart, live R8/R10/R26), report pages per group with filters (date range, Jalali month, category, brand, provider) and CSV/XLSX export; ★ reports in MVP, others behind a feature flag until data exists; role-based visibility.
- **Tests:** SKU page widgets: quantity, revenue, per-supplier split and gross/net margin match seeded order items; supplier page widgets: quantity, products sold, prices and revenue from this supplier match seeded order items; widgets render from seeded `rpt_*` data; live widgets query counts bounded; access by role; export matches filters.

> **Phase 8: External integrations, built last (owner, 2026-11-09).** Tipax (S22, S23, S27), Zarinpal (S26) and SMS (S28) are implemented after reporting, right before launch (S40 needs them). Until then the earlier steps run on stand-ins: SMS through the `log` driver (S17), checkout payments through the fake gateway and shipping through the own courier and the fallback rate table (S25), shipment status set by hand (S29).

### S22 Tipax client and reference sync
- **Moved to phase 8** (built after S42; owner, 2026-11-09).
- **Refs:** D6.1–D6.3, D6.6, D11.3 (A–C)
- **Build:** `TipaxClient` (token store, retry, timeouts, logging), commands `tipax:ping`, `tipax:sync-cities`, `tipax:sync-reference`, `tipax:register-origin`; carrier_city_mappings + admin mapping screen.
- **Tests:** `Http::fake` fixtures for token, cities, services; mapping by normalised name; token refresh.

### S23 Tipax quotes in checkout
- **Moved to phase 8**; until then checkout quotes the own courier (S24) and the fallback `RateTable` (S21).
- **Refs:** D6.4, D13.4 step 2
- **Build:** `TipaxCarrier::quote()` (unit conversion g→kg, mm→cm), `GetServicesBetweenCities`, shipping_quotes cache (20 min), shipping rules (free over X), fallback flag.
- **Tests:** quote mapping from fixture, cache hit, timeout → fallback estimate.

### S26 Zarinpal payments
- **Moved to phase 8**; until then checkout pays through the S25 fake gateway.
- **Refs:** D7 (all), D11.4, D16 (payments)
- **Build:** payments/payment_refunds, `ZarinpalGateway` implementing the S25 `PaymentGateway` contract (replaces the fake gateway outside local/testing) (request, verify, inquiry, unverified, reverse), callback controller, PaymentResult page, `OrderPaid` event, `ReconcileZarinpalPayments`, `ExpirePendingPayments`, `zarinpal:ping`.
- **Tests:** success 100, already verified 101, NOK, amount mismatch −50, reconciliation, reverse; 100 % branch coverage of verify.

### S27 Tipax shipment registration and tracking
- **Moved to phase 8**; until then shipment status is set by hand in the order admin (S29).
- **Refs:** D6.5, D6.7, D11.3 (D–E), D13.7
- **Build:** shipments/packages/events, `RegisterTipaxShipment` job (idempotent), callback + webhook controllers (secret header), `tipax:register-webhook`, polling job, status mapping → order status, label link, cancel action.
- **Tests:** idempotent registration, callback success/failure, webhook secret rejection, status mapping.

### S28 Notifications (SMS via Melli Payamak)
- **Moved to phase 8**; until then the `SmsSender` contract from S17 runs on `LogSmsSender`.
- **Refs:** D11.6, D3.6 (templates), D13.1
- **Build:** `SmsSender` contract, `LogSmsSender` + `MelipayamakSmsSender` (REST BaseServiceNumber with pattern bodyIds), templates ↔ bodyIds, notification logs (masked mobile, recId, code), `SendSmsJob` with retry rules, low-credit alert, `sms:test` command; events → SMS (otp, order_paid, order_shipped, order_out_for_delivery, order_delivered, payment_failed, stock_available).
- **Tests:** `Http::fake()` for success recId and each error class (retryable vs not), args joined with `;`, OTP never logged in plain text, queued sending.

### S40 Launch
- **Refs:** D11.9, A3, A4
- **Build:** production credentials (Tipax, Zarinpal, Melli Payamak), reporting warehouse first run verified, go-live checklist, demo data removed, soft-launch feedback fixes, browser smoke tests on mobile/tablet/desktop.
- **Tests:** full Pest browser smoke suite green against staging.

## E4. Using the `implement-roadmap-section` skill
- Run it with a step id or entity: `S07`, `S26`, or `Brands`.
- Entity names map to steps in **E5**; the full skill text is in **E6**.
- The skill locates `pharmacy-roadmap.md` in the repo (copy it to `docs/pharmacy-roadmap.md`), reads the step and referenced sections, and follows E1 exactly.
- Progress is tracked in `ROADMAP_PROGRESS.md`; the skill refuses to start a step whose dependencies (earlier phase steps) are not done, unless the user says to.

## E5. Entity → step index (run the skill with any name below)

| Entity / topic | Step | Module |
|---|---|---|
| Project setup, CI, versions | S00 | — |
| Module skeleton, arch tests | S01 | Shared |
| Layout, tokens, TallStackUI, RTL, Toman/Jalali helpers | S02 | Shared |
| Admin panel, admin users, MFA | S03 | Identity |
| Roles, permissions, activity log | S04 | Identity / System |
| Provinces, counties, cities, regions, neighbourhoods | S05 | Locations |
| Money, Persian normaliser, slugger, integration logs | S06 | Shared / System |
| Brands | S07 | Catalog |
| Categories, mega-menu, breadcrumbs | S08 | Catalog |
| Images / media, conversions, ThumbHash | S09 | Media |
| Products, variants, product FAQs, product relations | S10 | Catalog |
| Attributes, attribute values, tags (nutrient/need) | S11 | Catalog |
| PLP, filters, search, synonyms, search logs | S12 | Catalog / Search |
| Similar products (nightly batch) | S13 + D21.6 (SR4) | Search / Catalog |
| Meilisearch, hybrid/semantic search, embeddings (P2) | D21.9 SR6–SR9 | Search |
| PDP, reviews display, product questions, stock alerts, SKU images | S13 | Catalog |
| Collections, popular products (empty search box), home page builder (widgets, sliders), banners library and placements | S13b | Catalog / Search / Content |
| Providers, provider prices | S14 | Pricing |
| Pricing rules, pricing engine | S15 | Pricing |
| Price imports | S16 | Pricing |
| Customers, OTP codes, login | S17 | Identity |
| Addresses, account area, wishlist | S18 | Identity / Catalog |
| Cart, cart items, mini-cart | S19 | Cart |
| Coupons, coupon usages | S20 | Cart |
| Carriers, carrier services, shipping boxes, origins, shipping rules, box packer | S21 | Shipping |
| Tipax client, carrier city mappings, reference sync | S22 | Shipping |
| Shipping quotes (Tipax) | S23 | Shipping |
| Delivery zones, delivery slots, couriers | S24 | Shipping |
| Checkout wizard, orders, order items, order numbering | S25 | Checkout / Orders |
| Payments, payment refunds, Zarinpal | S26 | Payments |
| Shipments, shipment packages, shipment events, Tipax webhooks | S27 | Shipping |
| Notification templates, notification logs, SMS (Melli Payamak), OTP delivery | S28 | Notifications |
| Order admin, order and order item status logs, cancellations, customer order pages | S29 | Orders |
| Provider purchases (sourcing) | S30 | Orders / Pricing |
| Packing | S31 | Orders / Shipping |
| Return requests | S32 | Orders |
| Banners, home sections, flash deals, deals hub, bundles page | S33 | Content / Catalog |
| Static pages, FAQ, menus, contact messages | S34 | Content |
| Settings, social links, trust badges, pharmacists | S35 | Settings |
| SEO redirects, sitemap, meta, JSON-LD | S36 | Seo |
| Security hardening | S37 | System |
| Performance and caching | S38 | System |
| Horizon, Supervisor, monitoring, backups, deploy | S39 | System |
| Reporting warehouse (`rpt_*` tables, 12-hour refresh) | S41 | Reporting |
| Dashboard and reports R1–R27 | S42 | Reporting |
| Launch | S40 | — |

## E6. The `implement-roadmap-section` skill (full instructions)

Install the skill in your AI assistant (Claude: save it from the proposal card; other tools: add the text below as a reusable instruction/prompt). Usage examples:
- `implement-roadmap-section S07`
- `implement-roadmap-section Coupons`
- `implement-roadmap-section` (no argument: suggests the next unfinished step)

````markdown
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
````

---

# Appendix

## Glossary

| Term | Meaning |
|---|---|
| PLP / PDP | Product listing page / product detail page |
| OTC | Over-the-counter medicine (no prescription) |
| Rx | Prescription medicine |
| IRC | IFDA product registration code |
| e-Namad | Iranian e-commerce trust seal |
| COD | Cash on delivery |
| زودمصرف / کج‌جعبه | Near-expiry / damaged-box clearance items |

## Sources
- Competitor sites reviewed 2026-11-06: darunet.com, darukade.com, daroodrug.com, darochi.com, sarsoon.com, mosbatesabz.com
- Tipax documentation hub https://document.tipaxapi.org, pricing tool https://document.tipaxapi.org/pricing, Swagger V4/V3 https://omtestapi.tipax.ir/swagger/index.html
- Zarinpal docs https://www.zarinpal.com/docs/paymentGateway/ (connectToGateway, sandBox, errorList, reverse, unVerified, Inquiry)
- Iran locations https://github.com/sajaddp/list-of-cities-in-Iran, https://packagist.org/packages/zarbinco/laravel-iran-locations
- Filament v5 release (2026-01-16) https://laravel-news.com/filament-5 ; TallStackUI v4 https://tallstackui.com/docs
- MySQL vector functions/type https://dev.mysql.com/doc/refman/9.5/en/vector-functions.html, https://dev.mysql.com/doc/refman/9.5/en/vector.html ; Meilisearch hybrid search https://meilisearch.com/docs/capabilities/hybrid_search/overview ; Laravel Scout https://laravel.com/docs/scout
- Melli Payamak shared service line (pattern) web service: https://www.melipayamak.com/api/sendbybasenumber2/ and https://www.melipayamak.com/api/sendbybasenumber/
- IFDA online drug sale cancellation (2023) https://newspaper.irandaily.ir/7388/7/4931
