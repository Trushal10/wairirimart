# 07 — User Flow Diagrams

Consolidated Mermaid flowcharts for every user-facing journey in Vue-Ecommerce. See [08 Admin Workflow](08-admin-workflow.md) and [09 Customer Workflow](09-customer-workflow.md) for the narrative walkthroughs.

---

## 1. Customer registration + email verification

```mermaid
flowchart TD
    Start([Customer visits /register]) --> Fill[Enter name, email, phone, password]
    Fill --> Submit[POST /register]
    Submit --> Validate{Validation OK?}
    Validate -- No --> Errors[Show errors, back to form]
    Validate -- Yes --> CreateAccount[Create customer<br/>email_verified_at = NULL]
    CreateAccount --> IssueOTP[OtpService::issue<br/>persist to password_resets]
    IssueOTP --> SendMail[Queue OTP email + SMS]
    SendMail --> Redirect[Redirect to /verification]
    Redirect --> EnterOTP[Enter 6-digit OTP]
    EnterOTP --> VerifyPost[POST /verify-otp]
    VerifyPost --> CheckOTP{hash_equals<br/>+ TTL OK?}
    CheckOTP -- No --> OTPError[Show 'Invalid OTP']
    CheckOTP -- Yes --> Verify[Set email_verified_at = now]
    Verify --> Login[Auth::guard customer login]
    Login --> Profile([Redirect to /profile])
```

---

## 2. Customer login (email or phone)

```mermaid
flowchart TD
    Start([Customer visits /login]) --> Fill[Enter email or phone + password]
    Fill --> Submit[POST /login]
    Submit --> RateLimit{Throttle 5/min?}
    RateLimit -- Exceeded --> TooMany[HTTP 429]
    RateLimit -- OK --> Lookup[Find customer by email OR phone]
    Lookup --> Verify{Password matches?<br/>hash_check}
    Verify -- No --> Generic[Return generic<br/>'Invalid credentials.']
    Verify -- Yes --> Verified{email_verified_at set?}
    Verified -- No --> IssueOTP[Issue OTP<br/>redirect to /verification]
    Verified -- Yes --> Session[Regenerate session<br/>Auth::login customer]
    Session --> Home([Redirect to intended URL or /])
```

Key defence: password check happens **before** any OTP is issued to prevent OTP-bomb of known emails.

---

## 3. Password reset

```mermaid
flowchart TD
    Start([/forgot-password]) --> Enter[Enter email]
    Enter --> Post[POST /forgot-password]
    Post --> Generic[Always show generic success<br/>anti-enumeration]
    Post --> Exists{Customer exists?}
    Exists -- Yes --> Issue[Issue OTP<br/>persist token + otp]
    Exists -- Yes --> Mail[Queue reset email]
    Exists -- No --> NoOp[No-op]

    Mail --> Link([Customer clicks link<br/>with ?token=...])
    Link --> Form[Show reset form]
    Form --> Submit[PUT /reset-password<br/>token, otp, new password]
    Submit --> Verify{token found<br/>+ hash_equals otp<br/>+ TTL OK?}
    Verify -- No --> Err[Show 'Invalid or expired']
    Verify -- Yes --> Update[Update customer.password]
    Update --> Redirect([Redirect to /login])
```

---

## 4. Google OAuth login

```mermaid
flowchart TD
    Start([Click 'Continue with Google']) --> Redir[GET /auth/google/redirect]
    Redir --> Google[[Google OAuth consent]]
    Google -- authorized --> Callback[GET /auth/google/callback]
    Callback --> Fetch[Fetch user profile<br/>id, email, name]
    Fetch --> Find{Match customer by<br/>google_id OR email?}
    Find -- Found by google_id --> LoginExisting[Auth::login existing]
    Find -- Found by email --> LinkAndLogin[Set google_id, login]
    Find -- Not found --> Create[Create customer<br/>provider = google]
    Create --> LoginNew[Auth::login]
    LoginExisting --> Home
    LinkAndLogin --> Home
    LoginNew --> Home
    Home([Redirect to /])

    Callback -.-> Cancel[Callback with error → back to /login]
```

⚠️ **Known gap:** The email-match branch can allow an attacker to bind their Google account to a pre-existing password account. See [15 Security](15-security-architecture.md).

---

## 5. Browse → cart → checkout → payment

```mermaid
flowchart TD
    Home([/ Home]) --> Browse[Browse shop / product-detail]
    Browse --> Variant{Has variants?}
    Variant -- Yes --> Pick[Pick attributes]
    Variant -- No --> Add
    Pick --> Add[POST /add-to-cart]
    Add --> ShowCart[Cart badge updated]
    ShowCart --> Continue{Continue shopping?}
    Continue -- Yes --> Browse
    Continue -- No --> CartPage[GET /shopping-cart]
    CartPage --> Adjust[Adjust quantities / remove]
    Adjust --> Checkout[GET /checkout]
    Checkout --> Authed{Signed in?}
    Authed -- No --> Login[Redirect to /login → back]
    Authed -- Yes --> AddressStep
    Login --> AddressStep[Select or add address]
    AddressStep --> CouponOpt{Apply coupon?}
    CouponOpt -- Yes --> ApplyCoup[POST /apply-coupon]
    CouponOpt -- No --> PickMethod
    ApplyCoup --> PickMethod[Choose gateway: COD / Razorpay]
    PickMethod --> TOS[Tick agree_tos]
    TOS --> Place[POST /order]

    Place --> Method{payment_method?}
    Method -- COD --> CODFlow[Insert order/payment status=pending, decrement stock, notify]
    CODFlow --> Confirm([→ /order/confirmation/{orderNo}])

    Method -- Razorpay --> RZP[Create Razorpay order]
    RZP --> Checkout2[Show Razorpay checkout modal]
    Checkout2 --> Result{Payment result}
    Result -- Success --> Callback[POST /razorpay/callback]
    Callback --> Verify[Verify signature + amount + currency]
    Verify -- OK --> MarkPaid[Payment status → paid<br/>lockForUpdate on payment row]
    MarkPaid --> Notify[Queue OrderPlaced notif + SMS]
    Notify --> Confirm2([→ /order/confirmation/{orderNo}])
    Result -- Failure --> Fail[Payment status → failed, redirect back]
    Verify -- Bad --> Reject[HTTP 400 / 422]

    Confirm --> DoneA([End])
    Confirm2 --> DoneB([End])
```

Full detail in [09 Customer Workflow](09-customer-workflow.md) and [10 Payment Architecture](10-payment-architecture.md).

---

## 6. Order tracking

```mermaid
flowchart TD
    Start([Customer visits /track]) --> Form[Enter order# + email]
    Form --> Verify[POST /track]
    Verify --> Match{order_no + email match?}
    Match -- No --> Err[Show generic error]
    Match -- Yes --> Grant[Grant session for this orderNo]
    Grant --> Show[GET /track/{orderNo}]
    Show --> Poll[Optional: /track/{orderNo}/live<br/>JSON updates via JS polling]
    Poll --> Show
    Show --> Logout[POST /track/{orderNo}/logout]
    Logout --> Start
```

Session grant is scoped to a single `orderNo`; it does not authorise access to any other order.

---

## 7. Returns flow

```mermaid
flowchart TD
    Start([Customer opens delivered order]) --> Init[GET /returns/{orderNo}/new]
    Init --> Window{Within return<br/>window? default 14d}
    Window -- No --> NotAllowed[Show 'window closed']
    Window -- Yes --> Form[Pick items, reason, comment, photo]
    Form --> Submit[POST /returns/{orderNo}]
    Submit --> Create[Insert into returns<br/>+ return_items<br/>status=requested]
    Create --> AdminReview{Admin reviews}

    AdminReview -- Approve --> Approved[status=approved]
    AdminReview -- Reject --> Rejected[status=rejected + reason]
    Rejected --> DoneR([End])

    Approved --> Receive[Warehouse receives goods]
    Receive --> MarkRec[Admin: markReceived<br/>status=received]
    MarkRec --> Restock[Increment products.stock<br/>AND product_variants.stock]
    Restock --> Refund[Admin: markRefunded<br/>status=refunded]
    Refund --> RefundGate{Was Razorpay?}
    RefundGate -- Yes --> RzpRefund[Admin manually invokes Order refund action<br/>Razorpay refund API]
    RefundGate -- No --> CODRefund[Refund to source manually]
    RzpRefund --> DoneG([End])
    CODRefund --> DoneG

    Start2([Customer /returns/view/{returnNo}/cancel]) --> CancelChk{status == requested?}
    CancelChk -- Yes --> Cancelled[status=cancelled]
    CancelChk -- No --> NotCancelable[Reject]
```

⚠️ `markRefunded` **does not** auto-trigger a Razorpay refund; admin must invoke `POST /admin/a_orders/{order}/refund` separately (see [26 Known Limitations G-13](26-known-limitations.md)).

---

## 8. Wishlist toggle

```mermaid
flowchart TD
    Start([Click heart on product card]) --> Auth{Customer signed in?}
    Auth -- No --> Login[Redirect to /login → back]
    Auth -- Yes --> Toggle[POST /wishlist/toggle]
    Toggle --> Exists{Row in wishlists<br/>customer_id + product_id + variant_id?}
    Exists -- Yes --> Remove[DELETE row]
    Exists -- No --> Insert[INSERT row]
    Remove --> Resp[{added:false}]
    Insert --> Resp2[{added:true}]
```

---

## 9. Admin — assign AWB & dispatch shipment

```mermaid
flowchart TD
    Start([Admin opens order detail]) --> Check[GET /admin/a_orders/{order}/serviceability<br/>call courier API]
    Check --> Available{Serviceable?}
    Available -- No --> Choose[Choose different courier or split]
    Available -- Yes --> AssignAWB[POST /admin/a_orders/shipment/{s}/assign-awb]
    AssignAWB --> CallAdapter[ShiprocketAdapter::assignAwb]
    CallAdapter --> Store[Save awb_code, courier_id, courier_name,<br/>label_url, tracking_url]
    Store --> Notify[notifyCustomerShipped guarded by meta.ship_notified_at]
    Notify --> Label[POST /admin/a_orders/shipment/{s}/label<br/>generate label PDF]
    Label --> Pickup[POST /admin/a_orders/shipment/{s}/pickup<br/>request pickup]
    Pickup --> Sync[Periodic POST /admin/a_orders/shipment/{s}/sync<br/>or wait for webhook]
    Sync --> Done([Shipment tracked])
```

---

## 10. Razorpay callback + webhook race

```mermaid
sequenceDiagram
    autonumber
    participant B as Browser
    participant C as Client\PaymentController<br/>razorpayCallback
    participant W as Razorpay
    participant WH as razorpayWebhook
    participant DB as MySQL

    W-->>B: Redirect with razorpay_* params
    B->>C: POST /razorpay/callback

    par Callback path
        C->>W: fetch payment by id (server-side)
        W-->>C: {amount, currency, order_id, status}
        C->>C: verify signature, amount, currency, order_id
        C->>DB: lockForUpdate on payments row
        C->>DB: SET status = paid (if pending)
        C->>DB: COMMIT
        C->>DB: atomic UPDATE payment.meta.order_placed_notified_at
        DB-->>C: 1 row updated?
        C->>B: 302 → /order/confirmation
    and Webhook path
        W-->>WH: POST /webhooks/razorpay (payment.captured)
        WH->>DB: verify HMAC signature
        WH->>DB: lockForUpdate on payments row
        WH->>DB: dedupe by webhook_event_id in payment.meta.webhook_events
        WH->>DB: SET status = paid (if pending) - no-op if already paid
        WH->>DB: COMMIT
        WH-->>W: 200 OK
    end

    Note over C,WH: Exactly-one notification via atomic update on meta.order_placed_notified_at.
```

---

## 11. Refund (admin-initiated)

```mermaid
flowchart TD
    Start([Admin opens order detail]) --> Btn[Click Refund button]
    Btn --> Post[POST /admin/a_orders/{order}/refund<br/>amount?]
    Post --> Type{payment.type?}
    Type -- razorpay --> Rzp[RazorpayGateway::refund<br/>call Razorpay API]
    Rzp --> Cap[Cap amount at remaining refundable]
    Cap --> Persist[Update payment.refund_id, refunded_amount, refunded_at]
    Persist --> WH[Webhook confirms refund.processed<br/>dedup by refund_id]
    Persist --> Log[Log admin action]
    Type -- cod --> Manual[Manual - record refunded amount + reason]
    Log --> Done
    Manual --> Done([End])
```

---

## 12. Contact form

```mermaid
flowchart LR
    Visitor([Visitor]) --> Form[Contact form /contact]
    Form --> Post[POST /contact<br/>throttle 5/min]
    Post --> Validate{Valid?}
    Validate -- No --> Errs[Return with errors]
    Validate -- Yes --> Insert[INSERT into contacts]
    Insert --> Notify[Queue ContactNotification mail to store owner]
    Notify --> Thanks([Redirect back with success flash])
```

---

## 13. Admin login

```mermaid
flowchart TD
    Start([/admin/login]) --> Fill[email + password]
    Fill --> Submit[POST /admin/login<br/>throttle 5/min]
    Submit --> Attempt{Auth::attempt web guard}
    Attempt -- Fail --> Err[Generic error]
    Attempt -- Pass --> Role{user.role == 'admin'?}
    Role -- No --> Logout[Auth::logout → 403]
    Role -- Yes --> Session[Regenerate session]
    Session --> Redir([/admin/dashboard])
```

---

## 14. Session lifecycle (both guards)

- Session lifetime = 120 minutes.
- Session driver = database (`sessions` table).
- On login: `session::regenerate()`.
- On logout: `session::invalidate()` + `session::regenerateToken()`.
- Session cookies: `HttpOnly=true`, `SameSite=lax`, `Secure=true` (in production over HTTPS).
