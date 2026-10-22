# Lead collection setup

The order form on `index.html` posts each lead to a Google Apps Script web app,
which appends a row to a Google Sheet. Nothing runs on the VPS for this — the
browser talks to Google directly.

## One-time setup

1. Create a new Google Sheet. Name it anything, e.g. *Wellness1 Leads*.
2. In that sheet: **Extensions → Apps Script**. An editor opens with an empty
   `Code.gs`.
3. Delete whatever is in `Code.gs` and paste the entire contents of `Code.gs`
   from this folder. Save (⌘S).
4. **Deploy → New deployment**, gear icon → **Web app**, then set:
   - *Description*: anything, e.g. `lead intake v1`
   - *Execute as*: **Me**
   - *Who has access*: **Anyone** ← must be "Anyone", not "Anyone with Google
     account", or submissions from visitors will fail.
5. **Deploy**, then authorise when prompted. Google shows an "unverified app"
   warning because the script is your own and unpublished: *Advanced → Go to
   (project name)*. It is asking for permission to write to your own sheet.
6. Copy the **Web app URL**. It looks like
   `https://script.google.com/macros/s/AKfyc.../exec`.
7. Paste that URL into `index.html`, replacing `PASTE_NEW_WEB_APP_URL_HERE`
   (one occurrence, near the `SCRIPT_URL` constant), then redeploy the site.

## Verify it works

Open the Web app URL directly in a browser. It must print:

```json
{"status":"ok","sheet":"Leads","leads":0}
```

If you instead see a Google sign-in page or an error, *Who has access* is not
set to **Anyone** — fix that and redeploy (**Deploy → Manage deployments →
edit**).

Then submit a real order from the live site and confirm a row appears in the
**Leads** tab.

## Behaviour worth knowing

- **Columns** are created automatically on the first lead: `Received_At`,
  `Name`, `Phone`, `Age`, `Address`, `User_IP`, `Order_Time`, `User_Agent`.
  Don't rename or reorder them; the duplicate check reads `Phone` by position.
- **Duplicates** are rejected server-side when the same phone number orders
  twice inside 24 hours, and the page shows its existing "Duplicate Order
  Found" popup. Change `DUPLICATE_WINDOW_HOURS` at the top of `Code.gs` to
  adjust, or set it to `0` to accept every submission.
- **Email alerts** are off. Set `NOTIFY_EMAIL` in `Code.gs` to get a message
  per lead.
- **Failures are now visible.** The old integration used `mode: "no-cors"`,
  which made every request look successful — the customer saw the thank-you
  page whether or not the lead was saved. The form now reads the script's reply
  and only continues on success; if the lead cannot be saved it says so and
  keeps the customer on the form.

## After editing Code.gs

Changes do **not** go live on save. **Deploy → Manage deployments → edit (pencil)
→ Version: New version → Deploy.** The URL stays the same. Creating a *new
deployment* instead issues a different URL, which would also mean updating
`index.html`.
