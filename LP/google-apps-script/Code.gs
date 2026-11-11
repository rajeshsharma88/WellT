/**
 * Wellness1 — order form lead collection.
 *
 * Receives POSTs from the order form on wellness.aarogya.cloud and appends one
 * row per lead to the spreadsheet this script is bound to.
 *
 * Setup lives in README.md next to this file.
 */

// Tab the leads are written to. Created automatically if missing.
const SHEET_NAME = 'Leads';

// A repeat order from the same phone number inside this window is rejected as a
// duplicate instead of being appended. Set to 0 to accept everything.
const DUPLICATE_WINDOW_HOURS = 24;

// Leave empty for no email alerts. Otherwise every new lead sends a short
// notification here, so you can call the customer while they are still warm.
const NOTIFY_EMAIL = '';

const COLUMNS = [
  'Received_At',
  'Name',
  'Phone',
  'Age',
  'Address',
  'User_IP',
  'Order_Time',
  'User_Agent'
];

/**
 * Lead intake. Responds with JSON: {status: 'success' | 'duplicate' | 'error'}.
 */
function doPost(e) {
  // Concurrent submissions would otherwise race on getLastRow/appendRow and
  // could overwrite each other's row.
  const lock = LockService.getScriptLock();
  try {
    lock.waitLock(30000);
  } catch (err) {
    return jsonResponse({ status: 'error', message: 'Server busy, please retry.' });
  }

  try {
    const params = (e && e.parameter) || {};
    const name = String(params.Name || '').trim();
    const phone = String(params.Phone || '').replace(/\D/g, '');

    if (!name || !/^[6-9][0-9]{9}$/.test(phone)) {
      return jsonResponse({
        status: 'error',
        message: 'A name and a valid 10-digit mobile number are required.'
      });
    }

    const sheet = getLeadSheet();

    if (isRecentDuplicate(sheet, phone)) {
      return jsonResponse({ status: 'duplicate', message: 'Already ordered recently.' });
    }

    sheet.appendRow([
      new Date(),
      name,
      phone,
      String(params.Age || '').trim(),
      String(params.Address || '').trim(),
      String(params.User_IP || '').trim(),
      String(params.Order_Time || '').trim(),
      String(params.User_Agent || '').trim()
    ]);

    notify(name, phone, String(params.Address || ''));

    return jsonResponse({ status: 'success' });
  } catch (err) {
    // Surfaced to the page so a failure is visible instead of silent.
    return jsonResponse({ status: 'error', message: String(err) });
  } finally {
    lock.releaseLock();
  }
}

/**
 * Health check. Open the Web app URL in a browser to confirm the deployment is
 * live and public; it must print {"status":"ok",...}.
 */
function doGet() {
  const sheet = getLeadSheet();
  return jsonResponse({
    status: 'ok',
    sheet: SHEET_NAME,
    leads: Math.max(0, sheet.getLastRow() - 1)
  });
}

/** Returns the leads tab, creating and formatting it on first use. */
function getLeadSheet() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = ss.getSheetByName(SHEET_NAME);

  if (!sheet) {
    sheet = ss.insertSheet(SHEET_NAME);
  }

  if (sheet.getLastRow() === 0) {
    sheet.appendRow(COLUMNS);
    sheet.getRange(1, 1, 1, COLUMNS.length).setFontWeight('bold');
    sheet.setFrozenRows(1);
    // Keep phone numbers as text so Sheets never reformats them into
    // scientific notation or strips characters.
    sheet.getRange('C:C').setNumberFormat('@');
    sheet.getRange('A:A').setNumberFormat('yyyy-mm-dd hh:mm:ss');
    sheet.setColumnWidth(5, 320); // Address
  }

  return sheet;
}

/** True if this phone already appears within DUPLICATE_WINDOW_HOURS. */
function isRecentDuplicate(sheet, phone) {
  if (DUPLICATE_WINDOW_HOURS <= 0) return false;

  const lastRow = sheet.getLastRow();
  if (lastRow < 2) return false;

  const values = sheet.getRange(2, 1, lastRow - 1, 3).getValues();
  const cutoff = Date.now() - DUPLICATE_WINDOW_HOURS * 60 * 60 * 1000;

  // Rows are chronological, so walk backwards and stop at the window edge.
  for (let i = values.length - 1; i >= 0; i--) {
    const received = values[i][0];
    const when = received instanceof Date ? received.getTime() : 0;
    if (when && when < cutoff) break;
    if (String(values[i][2]).replace(/\D/g, '') === phone) return true;
  }

  return false;
}

/** Optional email alert; silently skipped when NOTIFY_EMAIL is empty. */
function notify(name, phone, address) {
  if (!NOTIFY_EMAIL) return;
  try {
    MailApp.sendEmail({
      to: NOTIFY_EMAIL,
      subject: 'New order: ' + name + ' (' + phone + ')',
      body: [
        'Name:    ' + name,
        'Phone:   ' + phone,
        'Address: ' + address,
        '',
        SpreadsheetApp.getActiveSpreadsheet().getUrl()
      ].join('\n')
    });
  } catch (err) {
    // A failed alert must never cost us the lead.
    console.error('Notification failed: ' + err);
  }
}

function jsonResponse(payload) {
  return ContentService
    .createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}
