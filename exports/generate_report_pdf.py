#!/usr/bin/env python3
"""
generate_report_pdf.py — WVR Income & Expenses Report PDF Generator
Called by report_pdf.php via shell_exec, receives data via stdin as JSON
"""

import sys
import json
from datetime import datetime
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.units import mm
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_RIGHT, TA_LEFT
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    HRFlowable, KeepTogether
)
from reportlab.graphics.shapes import Drawing, Rect, String
from reportlab.graphics import renderPDF

# ── Colors ────────────────────────────────────────────────────
DARK       = colors.HexColor('#1a1d27')
ACCENT     = colors.HexColor('#4f6ef7')
GREEN      = colors.HexColor('#16a34a')
GREEN_SOFT = colors.HexColor('#dcfce7')
RED        = colors.HexColor('#dc2626')
RED_SOFT   = colors.HexColor('#fee2e2')
ORANGE     = colors.HexColor('#ea580c')
ORANGE_SOFT= colors.HexColor('#fff7ed')
PURPLE     = colors.HexColor('#7c3aed')
BLUE_SOFT  = colors.HexColor('#eff6ff')
GRAY       = colors.HexColor('#6b7280')
GRAY_LIGHT = colors.HexColor('#f3f4f6')
BORDER     = colors.HexColor('#e5e7eb')
WHITE      = colors.white

def fmt(amount):
    return f"P{amount:,.2f}"

def build_pdf(data: dict, output_path: str):
    doc = SimpleDocTemplate(
        output_path,
        pagesize=A4,
        topMargin=15*mm, bottomMargin=15*mm,
        leftMargin=15*mm, rightMargin=15*mm,
        title=f"WVR Report — {data['period']}",
        author="White Villas Resort",
    )

    styles = getSampleStyleSheet()
    story  = []

    # ── Custom styles ─────────────────────────────────────────
    h1 = ParagraphStyle('H1', fontSize=18, textColor=DARK,
                         fontName='Helvetica-Bold', spaceAfter=2)
    h2 = ParagraphStyle('H2', fontSize=12, textColor=ACCENT,
                         fontName='Helvetica-Bold', spaceBefore=8, spaceAfter=4)
    h3 = ParagraphStyle('H3', fontSize=10, textColor=DARK,
                         fontName='Helvetica-Bold', spaceBefore=4, spaceAfter=2)
    sub = ParagraphStyle('Sub', fontSize=9, textColor=GRAY,
                          fontName='Helvetica', spaceAfter=1)
    normal = ParagraphStyle('Norm', fontSize=9, textColor=DARK,
                             fontName='Helvetica', leading=13)
    right  = ParagraphStyle('Right', fontSize=9, textColor=DARK,
                              fontName='Helvetica', alignment=TA_RIGHT)
    center = ParagraphStyle('Center', fontSize=9, textColor=GRAY,
                              fontName='Helvetica', alignment=TA_CENTER)
    small  = ParagraphStyle('Small', fontSize=8, textColor=GRAY,
                              fontName='Helvetica')

    W = A4[0] - 30*mm   # usable width

    # ── HEADER ────────────────────────────────────────────────
    header_data = [[
        Paragraph('<b>WHITE VILLAS RESORT</b>', ParagraphStyle('', fontSize=14,
            textColor=WHITE, fontName='Helvetica-Bold', leading=18)),
        Paragraph(f'<b>INCOME &amp; EXPENSES REPORT</b><br/>'
                  f'<font size="9" color="#ccd4ff">{data["period"]}</font>',
                  ParagraphStyle('', fontSize=11, textColor=WHITE,
                                  fontName='Helvetica-Bold', alignment=TA_RIGHT, leading=16))
    ]]
    header_tbl = Table(header_data, colWidths=[W*0.55, W*0.45])
    header_tbl.setStyle(TableStyle([
        ('BACKGROUND',   (0,0),(-1,-1), ACCENT),
        ('ROWBACKGROUNDS',(0,0),(-1,-1),[ACCENT]),
        ('TOPPADDING',   (0,0),(-1,-1), 12),
        ('BOTTOMPADDING',(0,0),(-1,-1), 12),
        ('LEFTPADDING',  (0,0),(-1,-1), 12),
        ('RIGHTPADDING', (0,0),(-1,-1), 12),
        ('ROUNDEDCORNERS',(0,0),(-1,-1),6),
    ]))
    story.append(header_tbl)

    # Sub-header info row
    generated = datetime.now().strftime('%B %d, %Y at %I:%M %p')
    story.append(Spacer(1, 4*mm))
    info_data = [[
        Paragraph(f'Generated: {generated}', small),
        Paragraph(f'Prepared by: {data.get("user","System")}', 
                  ParagraphStyle('', fontSize=8, textColor=GRAY, alignment=TA_RIGHT, fontName='Helvetica'))
    ]]
    info_tbl = Table(info_data, colWidths=[W*0.6, W*0.4])
    info_tbl.setStyle(TableStyle([
        ('TOPPADDING',(0,0),(-1,-1),0), ('BOTTOMPADDING',(0,0),(-1,-1),0),
    ]))
    story.append(info_tbl)
    story.append(HRFlowable(width=W, thickness=1, color=BORDER, spaceAfter=4*mm))

    # ── KPI SUMMARY CARDS ─────────────────────────────────────
    story.append(Paragraph('FINANCIAL SUMMARY', h2))

    kpi_inner_style = TableStyle([
        ('ALIGN',        (0,0),(-1,-1), 'CENTER'),
        ('VALIGN',       (0,0),(-1,-1), 'MIDDLE'),
        ('TOPPADDING',   (0,0),(-1,-1), 8),
        ('BOTTOMPADDING',(0,0),(-1,-1), 8),
        ('LEFTPADDING',  (0,0),(-1,-1), 6),
        ('RIGHTPADDING', (0,0),(-1,-1), 6),
        ('ROUNDEDCORNERS',(0,0),(-1,-1), 4),
    ])

    balance    = data['totalIncome'] - data['totalExpenses']
    margin_pct = round(balance / data['totalIncome'] * 100, 1) if data['totalIncome'] else 0
    bal_color  = GREEN if balance >= 0 else RED
    bal_bg     = GREEN_SOFT if balance >= 0 else RED_SOFT

    def kpi_cell(label, value, bg, val_color=DARK):
        return Table([[
            Paragraph(f'<font size="7" color="#6b7280">{label}</font><br/>'
                      f'<b><font size="13" color="{val_color.hexval() if hasattr(val_color,"hexval") else "#111827"}">'
                      f'{value}</font></b>', 
                      ParagraphStyle('kpi', alignment=TA_CENTER, fontName='Helvetica', leading=18))
        ]], colWidths=[(W-9*mm)/4])

    # Row 1: 4 main KPIs
    kpi_row1 = Table([[ 
        kpi_cell('TOTAL INCOME',    fmt(data['totalIncome']),    GREEN_SOFT, GREEN),
        Spacer(3*mm, 1),
        kpi_cell('TOTAL EXPENSES',  fmt(data['totalExpenses']),  RED_SOFT,   RED),
        Spacer(3*mm, 1),
        kpi_cell('NET BALANCE',     fmt(abs(balance)),            bal_bg,     bal_color),
        Spacer(3*mm, 1),
        kpi_cell('NET MARGIN',      f'{margin_pct}%',            BLUE_SOFT,  ACCENT),
    ]], colWidths=[(W-9*mm)/4, 3*mm, (W-9*mm)/4, 3*mm, (W-9*mm)/4, 3*mm, (W-9*mm)/4])
    kpi_row1.setStyle(TableStyle([
        ('TOPPADDING',   (0,0),(-1,-1), 0),
        ('BOTTOMPADDING',(0,0),(-1,-1), 0),
        ('LEFTPADDING',  (0,0),(-1,-1), 0),
        ('RIGHTPADDING', (0,0),(-1,-1), 0),
    ]))

    # Style each kpi cell individually
    for col_idx, (label, value, bg, vc) in enumerate([
        ('TOTAL INCOME',   fmt(data['totalIncome']),   GREEN_SOFT, GREEN),
        ('TOTAL EXPENSES', fmt(data['totalExpenses']), RED_SOFT,   RED),
        ('NET BALANCE',    fmt(abs(balance)),           bal_bg,     bal_color),
        ('NET MARGIN',     f'{margin_pct}%',           BLUE_SOFT,  ACCENT),
    ]):
        pass  # already built above via kpi_cell

    story.append(kpi_row1)
    story.append(Spacer(1, 3*mm))

    # Row 2: income breakdown + expense breakdown
    inc_data = [
        [Paragraph('<b>INCOME BREAKDOWN</b>', ParagraphStyle('', fontSize=8, textColor=ACCENT,
                    fontName='Helvetica-Bold')), '', ''],
        ['Cash Income',   '', Paragraph(fmt(data['cashTotal']),   right)],
        ['Card Income',   '', Paragraph(fmt(data['cardTotal']),   right)],
        ['Room Charged',  '', Paragraph(fmt(data['roomTotal']),   right)],
        [Paragraph('<b>Total</b>', ParagraphStyle('', fontSize=9, fontName='Helvetica-Bold')),
         '', Paragraph(f'<b>{fmt(data["totalIncome"])}</b>', 
                       ParagraphStyle('', fontSize=9, fontName='Helvetica-Bold', alignment=TA_RIGHT))],
    ]
    exp_data = [
        [Paragraph('<b>EXPENSE BREAKDOWN</b>', ParagraphStyle('', fontSize=8, textColor=RED,
                    fontName='Helvetica-Bold')), '', ''],
        ['Petty Expenses', '', Paragraph(fmt(data['pettyTotal']), right)],
        ['H/L Expenses',   '', Paragraph(fmt(data['hlTotal']),    right)],
        ['', '', ''],
        [Paragraph('<b>Total</b>', ParagraphStyle('', fontSize=9, fontName='Helvetica-Bold')),
         '', Paragraph(f'<b>{fmt(data["totalExpenses"])}</b>',
                       ParagraphStyle('', fontSize=9, fontName='Helvetica-Bold', alignment=TA_RIGHT))],
    ]

    CW = (W - 6*mm) / 2
    inc_tbl = Table(inc_data, colWidths=[CW*0.55, CW*0.05, CW*0.4])
    inc_tbl.setStyle(TableStyle([
        ('BACKGROUND',   (0,0),(-1,0),  GREEN_SOFT),
        ('BACKGROUND',   (0,4),(-1,4),  GREEN_SOFT),
        ('SPAN',         (0,0),(2,0)),
        ('SPAN',         (0,4),(2,4)),
        ('FONTSIZE',     (0,0),(-1,-1), 8),
        ('FONTNAME',     (0,0),(-1,-1), 'Helvetica'),
        ('TOPPADDING',   (0,0),(-1,-1), 4),
        ('BOTTOMPADDING',(0,0),(-1,-1), 4),
        ('LEFTPADDING',  (0,0),(-1,-1), 6),
        ('RIGHTPADDING', (0,0),(-1,-1), 6),
        ('BOX',          (0,0),(-1,-1), 0.5, BORDER),
        ('LINEBELOW',    (0,3),(-1,3),  0.5, BORDER),
        ('ROWBACKGROUNDS',(0,1),(2,3),  [WHITE, GRAY_LIGHT]),
    ]))

    exp_tbl = Table(exp_data, colWidths=[CW*0.55, CW*0.05, CW*0.4])
    exp_tbl.setStyle(TableStyle([
        ('BACKGROUND',   (0,0),(-1,0),  RED_SOFT),
        ('BACKGROUND',   (0,4),(-1,4),  RED_SOFT),
        ('SPAN',         (0,0),(2,0)),
        ('SPAN',         (0,4),(2,4)),
        ('FONTSIZE',     (0,0),(-1,-1), 8),
        ('FONTNAME',     (0,0),(-1,-1), 'Helvetica'),
        ('TOPPADDING',   (0,0),(-1,-1), 4),
        ('BOTTOMPADDING',(0,0),(-1,-1), 4),
        ('LEFTPADDING',  (0,0),(-1,-1), 6),
        ('RIGHTPADDING', (0,0),(-1,-1), 6),
        ('BOX',          (0,0),(-1,-1), 0.5, BORDER),
        ('LINEBELOW',    (0,3),(-1,3),  0.5, BORDER),
        ('ROWBACKGROUNDS',(0,1),(2,3),  [WHITE, GRAY_LIGHT]),
    ]))

    breakdown_row = Table([[inc_tbl, Spacer(6*mm,1), exp_tbl]],
                           colWidths=[CW, 6*mm, CW])
    breakdown_row.setStyle(TableStyle([
        ('TOPPADDING',   (0,0),(-1,-1), 0),
        ('BOTTOMPADDING',(0,0),(-1,-1), 0),
        ('LEFTPADDING',  (0,0),(-1,-1), 0),
        ('RIGHTPADDING', (0,0),(-1,-1), 0),
    ]))
    story.append(breakdown_row)
    story.append(Spacer(1, 5*mm))

    # ── WEEKLY SUMMARY ────────────────────────────────────────
    if data.get('weekly'):
        story.append(KeepTogether([
            Paragraph('WEEKLY SUMMARY', h2),
            _build_weekly_table(data['weekly'], W, normal, right, small)
        ]))
        story.append(Spacer(1, 5*mm))

    # ── DAILY DETAIL TABLE ────────────────────────────────────
    if data.get('daily'):
        story.append(Paragraph('DAILY DETAIL', h2))
        story.append(_build_daily_table(data['daily'], W, normal, right, small, balance))
        story.append(Spacer(1, 5*mm))

    # ── PETTY EXPENSES ────────────────────────────────────────
    if data.get('pettyRows'):
        story.append(KeepTogether([
            Paragraph('PETTY EXPENSES', h2),
            _build_transactions_table(data['pettyRows'], W, normal, right, small, RED)
        ]))
        story.append(Spacer(1, 5*mm))

    # ── H/L EXPENSES ─────────────────────────────────────────
    if data.get('hlRows'):
        story.append(KeepTogether([
            Paragraph('H/L EXPENSES', h2),
            _build_transactions_table(data['hlRows'], W, normal, right, small, ORANGE)
        ]))
        story.append(Spacer(1, 5*mm))

    # ── INCOME CASH ───────────────────────────────────────────
    if data.get('cashRows'):
        story.append(KeepTogether([
            Paragraph('INCOME — PAID BY CASH', h2),
            _build_income_table(data['cashRows'], W, normal, right, small, GREEN)
        ]))
        story.append(Spacer(1, 5*mm))

    # ── INCOME CARD ───────────────────────────────────────────
    if data.get('cardRows'):
        story.append(KeepTogether([
            Paragraph('INCOME — PAID BY CARD', h2),
            _build_income_table(data['cardRows'], W, normal, right, small, PURPLE)
        ]))
        story.append(Spacer(1, 5*mm))

    # ── ROOM CHARGED ──────────────────────────────────────────
    if data.get('roomRows'):
        story.append(KeepTogether([
            Paragraph('INCOME — ROOM CHARGED', h2),
            _build_room_table(data['roomRows'], W, normal, right, small)
        ]))
        story.append(Spacer(1, 5*mm))

    # ── FOOTER ────────────────────────────────────────────────
    story.append(HRFlowable(width=W, thickness=0.5, color=BORDER))
    story.append(Spacer(1, 2*mm))
    footer_data = [[
        Paragraph('White Villas Resort — Income &amp; Expenses Tracker', small),
        Paragraph(f'Report Period: {data["period"]}',
                  ParagraphStyle('', fontSize=8, textColor=GRAY, alignment=TA_RIGHT,
                                  fontName='Helvetica'))
    ]]
    footer_tbl = Table(footer_data, colWidths=[W*0.6, W*0.4])
    footer_tbl.setStyle(TableStyle([
        ('TOPPADDING',(0,0),(-1,-1),0),('BOTTOMPADDING',(0,0),(-1,-1),0),
    ]))
    story.append(footer_tbl)

    # ── Build ─────────────────────────────────────────────────
    def add_page_number(canvas, doc):
        canvas.saveState()
        canvas.setFont('Helvetica', 7)
        canvas.setFillColor(GRAY)
        canvas.drawRightString(A4[0]-15*mm, 8*mm,
                               f'Page {doc.page}  ·  White Villas Resort Confidential')
        canvas.restoreState()

    doc.build(story, onFirstPage=add_page_number, onLaterPages=add_page_number)


def _tbl_header(cols, widths, h_color=ACCENT):
    """Build a standard table header row."""
    header = [Paragraph(f'<b><font color="white">{c}</font></b>',
                         ParagraphStyle('th', fontSize=8, fontName='Helvetica-Bold',
                                         alignment=TA_CENTER)) for c in cols]
    return header


def _base_style(h_color=ACCENT):
    return TableStyle([
        ('BACKGROUND',    (0,0),(-1,0),  h_color),
        ('FONTNAME',      (0,0),(-1,-1), 'Helvetica'),
        ('FONTSIZE',      (0,0),(-1,-1), 8),
        ('TOPPADDING',    (0,0),(-1,-1), 4),
        ('BOTTOMPADDING', (0,0),(-1,-1), 4),
        ('LEFTPADDING',   (0,0),(-1,-1), 5),
        ('RIGHTPADDING',  (0,0),(-1,-1), 5),
        ('ROWBACKGROUNDS',(0,1),(-1,-1), [WHITE, GRAY_LIGHT]),
        ('BOX',           (0,0),(-1,-1), 0.5, BORDER),
        ('INNERGRID',     (0,0),(-1,-1), 0.25, BORDER),
        ('ALIGN',         (0,0),(-1,-1), 'LEFT'),
        ('VALIGN',        (0,0),(-1,-1), 'MIDDLE'),
    ])


def _build_weekly_table(weekly, W, normal, right, small):
    rows = [_tbl_header(['Week #', 'Income', 'Expenses', 'Balance', 'Margin %'],
                         [W*0.15, W*0.25, W*0.25, W*0.2, W*0.15])]
    for w in weekly:
        inc  = float(w.get('income',  0))
        exp  = float(w.get('expenses',0))
        bal  = inc - exp
        marg = round(bal/inc*100,1) if inc else 0
        bal_c = '#16a34a' if bal >= 0 else '#dc2626'
        rows.append([
            Paragraph(f'Week {w["week_number"]}', ParagraphStyle('', fontSize=8, fontName='Helvetica', alignment=TA_CENTER)),
            Paragraph(fmt(inc),  ParagraphStyle('', fontSize=8, fontName='Helvetica', alignment=TA_RIGHT, textColor=GREEN)),
            Paragraph(fmt(exp),  ParagraphStyle('', fontSize=8, fontName='Helvetica', alignment=TA_RIGHT, textColor=RED)),
            Paragraph(f'<font color="{bal_c}"><b>{fmt(abs(bal))}</b></font>',
                       ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold', alignment=TA_RIGHT)),
            Paragraph(f'{marg}%', ParagraphStyle('', fontSize=8, fontName='Helvetica', alignment=TA_CENTER)),
        ])
    # Totals row
    tot_inc = sum(float(w.get('income',0)) for w in weekly)
    tot_exp = sum(float(w.get('expenses',0)) for w in weekly)
    tot_bal = tot_inc - tot_exp
    rows.append([
        Paragraph('<b>TOTAL</b>', ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold', alignment=TA_CENTER)),
        Paragraph(f'<b>{fmt(tot_inc)}</b>', ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold', alignment=TA_RIGHT, textColor=GREEN)),
        Paragraph(f'<b>{fmt(tot_exp)}</b>', ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold', alignment=TA_RIGHT, textColor=RED)),
        Paragraph(f'<b>{fmt(abs(tot_bal))}</b>', ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold', alignment=TA_RIGHT)),
        '',
    ])
    t = Table(rows, colWidths=[W*0.15, W*0.25, W*0.25, W*0.2, W*0.15])
    s = _base_style()
    s.add('BACKGROUND', (0, len(rows)-1), (-1, len(rows)-1), GRAY_LIGHT)
    s.add('FONTNAME',   (0, len(rows)-1), (-1, len(rows)-1), 'Helvetica-Bold')
    t.setStyle(s)
    return t


def _build_daily_table(daily, W, normal, right, small, balance):
    rows = [_tbl_header(['Date', 'Income', 'Expenses', 'Daily Balance'],
                         [W*0.25, W*0.25, W*0.25, W*0.25])]
    for d in daily:
        inc = float(d.get('inc_total', 0))
        exp = float(d.get('exp_total', 0))
        bal = inc - exp
        bal_c = '#16a34a' if bal >= 0 else '#dc2626'
        sign = '+' if bal >= 0 else '-'
        rows.append([
            d.get('date',''),
            Paragraph(fmt(inc), ParagraphStyle('', fontSize=8, fontName='Helvetica', alignment=TA_RIGHT, textColor=GREEN)),
            Paragraph(fmt(exp), ParagraphStyle('', fontSize=8, fontName='Helvetica', alignment=TA_RIGHT, textColor=RED)),
            Paragraph(f'<font color="{bal_c}">{sign}{fmt(abs(bal))}</font>',
                       ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold', alignment=TA_RIGHT)),
        ])
    # Totals
    tot_inc = sum(float(d.get('inc_total',0)) for d in daily)
    tot_exp = sum(float(d.get('exp_total',0)) for d in daily)
    tot_bal = tot_inc - tot_exp
    bal_c = '#16a34a' if tot_bal >= 0 else '#dc2626'
    rows.append([
        Paragraph('<b>MONTHLY TOTAL</b>', ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold')),
        Paragraph(f'<b>{fmt(tot_inc)}</b>', ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold', alignment=TA_RIGHT, textColor=GREEN)),
        Paragraph(f'<b>{fmt(tot_exp)}</b>', ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold', alignment=TA_RIGHT, textColor=RED)),
        Paragraph(f'<b><font color="{bal_c}">{fmt(abs(tot_bal))}</font></b>',
                   ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold', alignment=TA_RIGHT)),
    ])
    t = Table(rows, colWidths=[W*0.25, W*0.25, W*0.25, W*0.25])
    s = _base_style()
    s.add('BACKGROUND', (0, len(rows)-1), (-1, len(rows)-1), GRAY_LIGHT)
    s.add('FONTNAME',   (0, len(rows)-1), (-1, len(rows)-1), 'Helvetica-Bold')
    t.setStyle(s)
    return t


def _build_transactions_table(rows_data, W, normal, right, small, color):
    rows = [_tbl_header(['Date', 'Description', 'Week', 'Amount'],
                         [W*0.18, W*0.52, W*0.1, W*0.2], h_color=color)]
    total = 0
    for r in rows_data:
        amt = float(r.get('amount', 0))
        total += amt
        rows.append([
            r.get('date',''),
            r.get('description',''),
            f"Wk {r.get('week_number','')}",
            Paragraph(fmt(amt), ParagraphStyle('', fontSize=8, fontName='Helvetica', alignment=TA_RIGHT)),
        ])
    rows.append([
        '', Paragraph('<b>TOTAL</b>', ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold')), '',
        Paragraph(f'<b>{fmt(total)}</b>', ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold', alignment=TA_RIGHT, textColor=color)),
    ])
    t = Table(rows, colWidths=[W*0.18, W*0.52, W*0.1, W*0.2])
    s = _base_style(h_color=color)
    s.add('BACKGROUND', (0, len(rows)-1), (-1, len(rows)-1), GRAY_LIGHT)
    t.setStyle(s)
    return t


def _build_income_table(rows_data, W, normal, right, small, color):
    rows = [_tbl_header(['Date', 'Category', 'Week', 'Amount'],
                         [W*0.18, W*0.52, W*0.1, W*0.2], h_color=color)]
    total = 0
    for r in rows_data:
        amt = float(r.get('amount', 0))
        total += amt
        rows.append([
            r.get('date',''),
            r.get('category', r.get('description','')),
            f"Wk {r.get('week_number','')}",
            Paragraph(fmt(amt), ParagraphStyle('', fontSize=8, fontName='Helvetica', alignment=TA_RIGHT)),
        ])
    rows.append([
        '', Paragraph('<b>TOTAL</b>', ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold')), '',
        Paragraph(f'<b>{fmt(total)}</b>', ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold', alignment=TA_RIGHT, textColor=color)),
    ])
    t = Table(rows, colWidths=[W*0.18, W*0.52, W*0.1, W*0.2])
    s = _base_style(h_color=color)
    s.add('BACKGROUND', (0, len(rows)-1), (-1, len(rows)-1), GRAY_LIGHT)
    t.setStyle(s)
    return t


def _build_room_table(rows_data, W, normal, right, small):
    color = colors.HexColor('#0891b2')
    rows = [_tbl_header(['Date', 'Room Reference', 'Week', 'Amount'],
                         [W*0.18, W*0.52, W*0.1, W*0.2], h_color=color)]
    total = 0
    for r in rows_data:
        amt = float(r.get('amount', 0))
        total += amt
        rows.append([
            r.get('date',''),
            r.get('room_reference','—') or '—',
            f"Wk {r.get('week_number','')}",
            Paragraph(fmt(amt), ParagraphStyle('', fontSize=8, fontName='Helvetica', alignment=TA_RIGHT)),
        ])
    rows.append([
        '', Paragraph('<b>TOTAL</b>', ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold')), '',
        Paragraph(f'<b>{fmt(total)}</b>', ParagraphStyle('', fontSize=8, fontName='Helvetica-Bold', alignment=TA_RIGHT, textColor=color)),
    ])
    t = Table(rows, colWidths=[W*0.18, W*0.52, W*0.1, W*0.2])
    s = _base_style(h_color=color)
    s.add('BACKGROUND', (0, len(rows)-1), (-1, len(rows)-1), GRAY_LIGHT)
    t.setStyle(s)
    return t


if __name__ == '__main__':
    raw  = sys.stdin.read()
    data = json.loads(raw)
    out  = sys.argv[1] if len(sys.argv) > 1 else '/tmp/wvr_report.pdf'
    build_pdf(data, out)
    print('OK')
