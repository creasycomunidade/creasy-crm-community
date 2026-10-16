import ExcelJS from 'exceljs';

/** @param {number} rows @param {number} cols */
export function emptyMatrix(rows, cols = 4) {
    return Array.from({ length: rows }, () => Array.from({ length: cols }, () => ''));
}

function cellDisplayValue(cell) {
    if (cell == null || cell.value === null || cell.value === undefined) {
        return '';
    }
    const v = cell.value;
    if (typeof v === 'object') {
        if (v.richText) {
            return v.richText.map((p) => p.text).join('');
        }
        if (v.text) {
            return String(v.text);
        }
        if (v.result !== undefined) {
            return String(v.result);
        }
        if (v.hyperlink && v.text !== undefined) {
            return String(v.text);
        }
    }
    return String(v);
}

function headerIndex(headers, patterns) {
    const norm = headers.map((h) =>
        String(h ?? '')
            .toLowerCase()
            .replace(/[^a-z0-9\s]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim(),
    );
    for (let i = 0; i < norm.length; i++) {
        const h = norm[i];
        for (const p of patterns) {
            if (h === p || h.includes(p) || p.includes(h)) {
                return i;
            }
        }
    }
    return -1;
}

function tagNameFromRaw(raw, tags) {
    const s = String(raw ?? '').trim();
    if (!s) {
        return '';
    }
    const asNum = Number.parseInt(s, 10);
    if (!Number.isNaN(asNum) && String(asNum) === s) {
        const t = tags.find((x) => x.id === asNum);
        return t ? t.name : '';
    }
    const hit = tags.find((t) => t.name.toLowerCase() === s.toLowerCase());
    return hit ? hit.name : s;
}

function segmentNameFromRaw(raw, segments) {
    const s = String(raw ?? '').trim();
    if (!s) {
        return '';
    }
    const asNum = Number.parseInt(s, 10);
    if (!Number.isNaN(asNum) && String(asNum) === s) {
        const seg = segments.find((x) => x.id === asNum);
        return seg ? seg.name : '';
    }
    const hit = segments.find((seg) => seg.name.toLowerCase() === s.toLowerCase());
    return hit ? hit.name : s;
}

/**
 * Read first worksheet into Handsontable rows: [name, phone, tagName, segmentName].
 * @param {ArrayBuffer} arrayBuffer
 * @param {Array<{id:number,name:string}>} tags
 * @param {Array<{id:number,name:string}>} segments
 * @returns {string[][] | null}
 */
export async function parseWorkbookToMatrix(arrayBuffer, tags, segments) {
    const wb = new ExcelJS.Workbook();
    await wb.xlsx.load(arrayBuffer);
    const ws = wb.worksheets[0];
    if (!ws) {
        return null;
    }

    let maxCol = 0;
    ws.eachRow((row) => {
        maxCol = Math.max(maxCol, row.cellCount);
    });
    if (maxCol < 1) {
        return null;
    }

    const matrix = [];
    ws.eachRow((row) => {
        const r = [];
        for (let c = 1; c <= maxCol; c++) {
            r.push(cellDisplayValue(row.getCell(c)));
        }
        matrix.push(r);
    });

    if (!matrix.length) {
        return null;
    }

    const headers = matrix[0].map((c) => String(c ?? ''));
    const nameI = headerIndex(headers, ['name', 'fullname', 'full name']);
    const phoneI = headerIndex(headers, ['phone', 'mobile', 'tel', 'e164', 'number']);
    const tagI = headerIndex(headers, ['contact list', 'contact lists', 'list', 'tag', 'tags']);
    const segI = headerIndex(headers, ['segment', 'segments']);

    if (phoneI < 0) {
        return null;
    }

    const out = [];
    for (let i = 1; i < matrix.length; i++) {
        const row = matrix[i] || [];
        const name = nameI >= 0 ? String(row[nameI] ?? '').trim() : '';
        const phone = phoneI >= 0 ? String(row[phoneI] ?? '').trim() : '';
        const tagRaw = tagI >= 0 ? row[tagI] : '';
        const segRaw = segI >= 0 ? row[segI] : '';
        if (!name && !phone && !String(tagRaw ?? '').trim() && !String(segRaw ?? '').trim()) {
            continue;
        }
        out.push([name, phone, tagNameFromRaw(tagRaw, tags), segmentNameFromRaw(segRaw, segments)]);
    }

    return out.length ? out : emptyMatrix(10);
}

export async function downloadSampleWorkbook() {
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Contacts');
    ws.addRow([
        'Name',
        'Phone (E.164, country code required)',
        'Contact list (tag name, optional)',
        'Segment (static segment name, optional)',
    ]);
    ws.addRow(['Jane Doe', '+15551234567', '', '']);
    ws.getRow(1).font = { bold: true };
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'contacts-import-sample.xlsx';
    a.click();
    URL.revokeObjectURL(url);
}

/**
 * Map editable table data to API payload (tag/segment names → ids).
 * @param {string[][]} data
 * @param {Array<{id:number,name:string}>} tags
 * @param {Array<{id:number,name:string}>} segments
 */
export function matrixToPayload(data, tags, segments) {
    return data.map((row) => {
        const name = String(row[0] ?? '').trim();
        const phone = String(row[1] ?? '').trim();
        const tagName = String(row[2] ?? '').trim();
        const segName = String(row[3] ?? '').trim();
        const tag = tags.find((t) => t.name.toLowerCase() === tagName.toLowerCase());
        const seg = segments.find((s) => s.name.toLowerCase() === segName.toLowerCase());
        return {
            name: name || null,
            phone_e164: phone || null,
            tag_id: tag ? tag.id : null,
            segment_id: seg ? seg.id : null,
        };
    });
}
