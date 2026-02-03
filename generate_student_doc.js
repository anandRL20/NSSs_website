/**
 * generate_student_doc.js
 * 
 * Usage (called from PHP via shell_exec):
 *   node generate_student_doc.js '<json>' /path/to/output.docx
 * 
 * Install dependency on your server once:
 *   npm install docx
 */

const fs   = require('fs');
const path = require('path');
const {
    Document, Packer, Paragraph, TextRun,
    Table, TableRow, TableCell,
    AlignmentType, BorderStyle, WidthType, ShadingType,
    Header, Footer, PageNumber
} = require('docx');

// ── Parse CLI args ──────────────────────────────────────────────
const data      = JSON.parse(process.argv[2]);
const outputPath = process.argv[3];

const student = data.student;   // { full_name, email, username, created_at, photo, roll_number, course, year, department, phone, date_of_birth, address }
const marks   = data.marks;     // [{ subject_name, semester, marks_obtained, max_marks }, ...]

// ── Helpers ─────────────────────────────────────────────────────
const GOLD   = '1A1A2E';   // dark navy (used for header bg)
const ACCENT = '0F4C75';   // accent blue
const LIGHT  = 'D5E8F0';   // light blue for alt rows / header cells
const WHITE  = 'FFFFFF';
const GRAY   = 'F4F6F8';   // alternating row

const border = { style: BorderStyle.SINGLE, size: 1, color: 'B0C4D8' };
const borders = { top: border, bottom: border, left: border, right: border };

function infoRow(label, value) {
    // Two-column row: label (left, gray bg) | value (right, white bg)
    return new TableRow({
        children: [
            new TableCell({
                borders,
                width: { size: 3120, type: WidthType.DXA },
                shading: { fill: GRAY, type: ShadingType.CLEAR },
                margins: { top: 100, bottom: 100, left: 140, right: 140 },
                children: [new Paragraph({
                    children: [new TextRun({ text: label, bold: true, size: 22, font: 'Arial', color: ACCENT })]
                })]
            }),
            new TableCell({
                borders,
                width: { size: 6240, type: WidthType.DXA },
                shading: { fill: WHITE, type: ShadingType.CLEAR },
                margins: { top: 100, bottom: 100, left: 140, right: 140 },
                children: [new Paragraph({
                    children: [new TextRun({ text: value || '—', size: 22, font: 'Arial', color: '1A1A2E' })]
                })]
            })
        ]
    });
}

// ── Build Document ──────────────────────────────────────────────
const children = [];

// ── Title ───────────────────────────────────────────────────────
children.push(
    new Paragraph({
        alignment: AlignmentType.CENTER,
        spacing: { after: 60 },
        children: [new TextRun({ text: 'Student Information Report', bold: true, size: 48, font: 'Arial', color: ACCENT })]
    }),
    new Paragraph({
        alignment: AlignmentType.CENTER,
        spacing: { after: 400 },
        children: [new TextRun({ text: `Generated on ${new Date().toLocaleDateString('en-IN', { year:'numeric', month:'long', day:'numeric' })}`, size: 20, font: 'Arial', color: '666666' })]
    })
);

// ── Personal Details Table ─────────────────────────────────────
children.push(
    new Paragraph({
        spacing: { before: 200, after: 140 },
        children: [new TextRun({ text: 'Personal Details', bold: true, size: 26, font: 'Arial', color: ACCENT })]
    }),
    new Table({
        width: { size: 100, type: WidthType.PERCENTAGE },
        columnWidths: [3120, 6240],  // sums to 9360 (US Letter content width at 1″ margins)
        rows: [
            infoRow('Full Name',  student.full_name),
            infoRow('Email',      student.email),
            infoRow('Username',   student.username),
            infoRow('Joined',     student.created_at)
        ]
    })
);

// ── Academic Details Table ──────────────────────────────────────
if (student.roll_number) {
    children.push(
        new Paragraph({
            spacing: { before: 360, after: 140 },
            children: [new TextRun({ text: 'Academic Details', bold: true, size: 26, font: 'Arial', color: ACCENT })]
        }),
        new Table({
            width: { size: 100, type: WidthType.PERCENTAGE },
            columnWidths: [3120, 6240],
            rows: [
                infoRow('Roll Number',  student.roll_number),
                infoRow('Course',       student.course),
                infoRow('Year',         student.year),
                infoRow('Department',   student.department),
                infoRow('Phone',        student.phone),
                infoRow('Date of Birth',student.date_of_birth),
                infoRow('Address',      student.address)
            ]
        })
    );
}

// ── Marks Table ─────────────────────────────────────────────────
children.push(
    new Paragraph({
        spacing: { before: 360, after: 140 },
        children: [new TextRun({ text: 'Academic Performance', bold: true, size: 26, font: 'Arial', color: ACCENT })]
    })
);

if (marks.length > 0) {
    const COL = [2340, 1870, 1870, 1560, 1720]; // sums to 9360

    // Header row
    const headers = ['Subject', 'Semester', 'Marks Obtained', 'Max Marks', 'Percentage'];
    const headerRow = new TableRow({
        children: headers.map((h, i) =>
            new TableCell({
                borders,
                width: { size: COL[i], type: WidthType.DXA },
                shading: { fill: ACCENT, type: ShadingType.CLEAR },
                margins: { top: 100, bottom: 100, left: 120, right: 120 },
                children: [new Paragraph({
                    alignment: AlignmentType.CENTER,
                    children: [new TextRun({ text: h, bold: true, size: 21, font: 'Arial', color: WHITE })]
                })]
            })
        )
    });

    // Data rows
    const dataRows = marks.map((m, idx) => {
        const pct = m.max_marks > 0
            ? (Math.round((m.marks_obtained / m.max_marks) * 10000) / 100).toFixed(2) + '%'
            : '—';
        const rowBg = idx % 2 === 0 ? WHITE : GRAY;
        const cells = [m.subject_name, m.semester, String(m.marks_obtained), String(m.max_marks), pct];
        return new TableRow({
            children: cells.map((val, i) =>
                new TableCell({
                    borders,
                    width: { size: COL[i], type: WidthType.DXA },
                    shading: { fill: rowBg, type: ShadingType.CLEAR },
                    margins: { top: 80, bottom: 80, left: 120, right: 120 },
                    children: [new Paragraph({
                        alignment: i >= 2 ? AlignmentType.CENTER : AlignmentType.LEFT,
                        children: [new TextRun({
                            text: val,
                            size: 21,
                            font: 'Arial',
                            color: i === 4 ? ACCENT : '1A1A2E',  // percentage in accent color
                            bold: i === 4
                        })]
                    })]
                })
            )
        });
    });

    children.push(
        new Table({
            width: { size: 100, type: WidthType.PERCENTAGE },
            columnWidths: COL,
            rows: [headerRow, ...dataRows]
        })
    );
} else {
    children.push(
        new Paragraph({
            alignment: AlignmentType.CENTER,
            spacing: { before: 200 },
            children: [new TextRun({ text: 'No marks have been added yet.', size: 22, font: 'Arial', color: '999999', italics: true })]
        })
    );
}

// ── Assemble & Write ────────────────────────────────────────────
const doc = new Document({
    sections: [{
        properties: {
            page: {
                size:   { width: 11906, height: 16838 },   // A4
                margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 }
            }
        },
        headers: {
            default: new Header({
                children: [new Paragraph({
                    alignment: AlignmentType.RIGHT,
                    children: [new TextRun({ text: 'Admin Panel – Student Report', size: 18, font: 'Arial', color: '999999', italics: true })]
                })]
            })
        },
        footers: {
            default: new Footer({
                children: [new Paragraph({
                    alignment: AlignmentType.CENTER,
                    children: [
                        new TextRun({ text: 'Page ', size: 18, font: 'Arial', color: '999999' }),
                        new TextRun({ children: [PageNumber.CURRENT], size: 18, font: 'Arial', color: '999999' })
                    ]
                })]
            })
        },
        children
    }]
});

Packer.toBuffer(doc).then(buffer => {
    fs.writeFileSync(outputPath, buffer);
    console.log('OK');
}).catch(err => {
    console.error(err.message);
    process.exit(1);
});