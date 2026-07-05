/**
 * Google Sheets Apps Script backend for exam submissions.
 *
 * 1) Open Google Sheets and create a sheet named "ExamSubmissions".
 * 2) In Apps Script, paste this code and set SPREADSHEET_ID.
 * 3) Deploy as Web App: Execute as "Me", access "Anyone".
 * 4) Use the web app URL as GOOGLE_SHEETS_EXAM_WEBHOOK in submit_exam.php.
 */

const SPREADSHEET_ID = 'YOUR_SPREADSHEET_ID_HERE';
const SHEET_NAME = 'ExamSubmissions';

function doPost(e) {
  const result = { success: false, message: 'Unable to process request.' };

  try {
    const payload = parseRequestData(e);
    validatePayload(payload);

    const sheet = SpreadsheetApp.openById(SPREADSHEET_ID).getSheetByName(SHEET_NAME)
      || SpreadsheetApp.openById(SPREADSHEET_ID).insertSheet(SHEET_NAME);

    const headers = ['submitted_at', 'name', 'email', 'student_id', 'exam_title', 'access_code', 'score', 'total_questions', 'answers', 'remarks', 'source'];
    maybeWriteHeaders(sheet, headers);

    const row = [
      payload.submitted_at || new Date(),
      payload.name || '',
      payload.email || '',
      payload.student_id || '',
      payload.exam_title || '',
      payload.access_code || '',
      payload.score || '',
      payload.total_questions || '',
      payload.answers || '',
      payload.remarks || '',
      payload.source || 'website',
    ];

    sheet.appendRow(row);
    result.success = true;
    result.message = 'Submission saved to Google Sheets.';
  } catch (err) {
    result.message = err.message || 'An error occurred while saving submission.';
  }

  return ContentService.createTextOutput(JSON.stringify(result))
    .setMimeType(ContentService.MimeType.JSON);
}

function parseRequestData(e) {
  if (e.postData && e.postData.contents) {
    try {
      return JSON.parse(e.postData.contents);
    } catch (err) {
      // fallback to parameters if body is not JSON
    }
  }

  const params = e.parameter || {};
  return {
    name: params.name || '',
    email: params.email || '',
    student_id: params.student_id || '',
    exam_title: params.exam_title || '',
    access_code: params.access_code || '',
    score: params.score || '',
    total_questions: params.total_questions || '',
    answers: params.answers || '',
    remarks: params.remarks || '',
    submitted_at: params.submitted_at || new Date().toISOString(),
    source: params.source || 'website',
  };
}

function validatePayload(payload) {
  if (!payload.name || !payload.email || !payload.exam_title || !payload.access_code) {
    throw new Error('የሚገባ መረጃ አልተሰጠም። name, email, exam_title, access_code ይፈልጋሉ።');
  }
}

function maybeWriteHeaders(sheet, headers) {
  const existingHeaders = sheet.getRange(1, 1, 1, headers.length).getValues()[0];
  const needsHeaders = existingHeaders.some((value, index) => !value || String(value).trim() === '');
  if (needsHeaders) {
    sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
  }
}
