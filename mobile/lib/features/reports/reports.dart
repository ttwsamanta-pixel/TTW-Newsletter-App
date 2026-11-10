import 'dart:math';

import 'package:flutter/material.dart';

import '../../core/auth.dart';
import '../../widgets/ui.dart';
import '../../widgets/manager_ui.dart';
import '../newsletters/details.dart';

class ReportsScreen extends StatefulWidget {
  final Auth auth;
  const ReportsScreen({super.key, required this.auth});
  @override
  State<ReportsScreen> createState() => _ReportsState();
}

class _ReportsState extends State<ReportsScreen> {
  DateTime month = DateTime(DateTime.now().year, DateTime.now().month);
  int tab = 0;
  String get keyMonth =>
      '${month.year}-${month.month.toString().padLeft(2, '0')}';
  void shift(int n) =>
      setState(() => month = DateTime(month.year, month.month + n));
  @override
  Widget build(BuildContext context) => RemotePage(
    auth: widget.auth,
    title: 'Reports',
    route: '/reports',
    query: {'month': keyMonth},
    content: (ctx, data, reload) {
      final totals = data['totals'] as Map;
      final days = data['days'] as List;
      final count = number(totals['total']);
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              IconButton(
                onPressed: () => shift(-1),
                icon: const Icon(Icons.chevron_left),
              ),
              Text(
                keyMonth,
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                ),
              ),
              IconButton(
                onPressed: () => shift(1),
                icon: const Icon(Icons.chevron_right),
              ),
            ],
          ),
          SegmentedButton<int>(
            segments: const [
              ButtonSegment(value: 0, label: Text('Monthly')),
              ButtonSegment(value: 1, label: Text('Calendar')),
              ButtonSegment(value: 2, label: Text('Employees')),
            ],
            selected: {tab},
            onSelectionChanged: (v) => setState(() => tab = v.first),
          ),
          const SizedBox(height: 24),
          if (tab == 0) ...[
            Center(
              child: SizedBox(
                width: 170,
                height: 170,
                child: CustomPaint(
                  painter: DonutPainter(
                    number(totals['morning']),
                    number(totals['evening']),
                  ),
                  child: Center(
                    child: Text(
                      '$count\nTotal',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 23,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 20),
            Row(
              children: [
                _metric('Morning', number(totals['morning']), blue),
                const SizedBox(width: 12),
                _metric('Evening', number(totals['evening']), Colors.orange),
              ],
            ),
            const SizedBox(height: 24),
            const Text(
              'Daily production',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
            ),
            const SizedBox(height: 12),
            SizedBox(
              height: 150,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  for (final day in days)
                    Expanded(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 2),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.end,
                          children: [
                            Text(
                              '${day['total']}',
                              style: const TextStyle(fontSize: 10),
                            ),
                            Container(
                              height:
                                  110 *
                                  number(day['total']) /
                                  max(
                                    1,
                                    days.fold<int>(
                                      0,
                                      (m, d) => max(m, number(d['total'])),
                                    ),
                                  ),
                              decoration: BoxDecoration(
                                color: blue,
                                borderRadius: BorderRadius.circular(4),
                              ),
                            ),
                            Text(
                              '${DateTime.parse(day['newsletter_date']).day}',
                              style: const TextStyle(fontSize: 9),
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
            if (days.isEmpty)
              const EmptyState(
                'No Data Found',
                'No newsletters for this month.',
              ),
          ],
          if (tab == 1) ...[
            CalendarDatePicker(
              key: ValueKey(keyMonth),
              initialDate: month,
              firstDate: DateTime(month.year, month.month),
              lastDate: DateTime(month.year, month.month + 1, 0),
              onDateChanged: (date) => openPage(
                context,
                ReportDayScreen(
                  auth: widget.auth,
                  date: date.toIso8601String().substring(0, 10),
                ),
              ),
            ),
            for (final day in days)
              Card(
                child: ListTile(
                  title: Text(day['newsletter_date']),
                  subtitle: Text(
                    '${day['morning']} Morning · ${day['evening']} Evening',
                  ),
                  trailing: Text('${day['total']}'),
                  onTap: () => openPage(
                    context,
                    ReportDayScreen(
                      auth: widget.auth,
                      date: day['newsletter_date'],
                    ),
                  ),
                ),
              ),
          ],
          if (tab == 2) ...[
            for (final employee in data['employees'] as List)
              Card(
                child: ListTile(
                  leading: CircleAvatar(
                    child: Text('${employee['employee_name']}'.substring(0, 1)),
                  ),
                  title: Text(employee['employee_name']),
                  subtitle: Text(
                    '${employee['morning_count']} Morning · ${employee['evening_count']} Evening',
                  ),
                  trailing: Text(
                    '${employee['total_count']}',
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      color: blue,
                    ),
                  ),
                  onTap: () => openPage(
                    context,
                    RemotePage(
                      auth: widget.auth,
                      title: employee['employee_name'],
                      route: '/reports',
                      query: {
                        'month': keyMonth,
                        'employee': '${employee['user_id']}',
                      },
                      content: (ctx, r, _) => Column(
                        children: [
                          for (final n in r['details'] as List)
                            NewsletterCard(
                              auth: widget.auth,
                              n: n,
                              onTap: () => openPage(
                                ctx,
                                NewsletterDetailsScreen(
                                  auth: widget.auth,
                                  id: number(n['id']),
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
          ],
          const SizedBox(height: 30),
        ],
      );
    },
  );
  Widget _metric(String label, int value, Color color) => Expanded(
    child: Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          children: [
            Text(
              '$value',
              style: TextStyle(
                fontSize: 27,
                fontWeight: FontWeight.bold,
                color: color,
              ),
            ),
            Text(label),
          ],
        ),
      ),
    ),
  );
}

class DonutPainter extends CustomPainter {
  final int morning, evening;
  DonutPainter(this.morning, this.evening);
  @override
  void paint(Canvas canvas, Size size) {
    final rect = Rect.fromLTWH(10, 10, size.width - 20, size.height - 20);
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 18;
    canvas.drawArc(
      rect,
      0,
      2 * pi,
      false,
      paint..color = const Color(0xffe4ebfa),
    );
    final total = morning + evening;
    if (total == 0) return;
    canvas.drawArc(
      rect,
      -pi / 2,
      2 * pi * morning / total,
      false,
      paint..color = blue,
    );
    canvas.drawArc(
      rect,
      -pi / 2 + 2 * pi * morning / total,
      2 * pi * evening / total,
      false,
      paint..color = Colors.orange,
    );
  }

  @override
  bool shouldRepaint(DonutPainter old) =>
      old.morning != morning || old.evening != evening;
}

class ReportDayScreen extends StatelessWidget {
  final Auth auth;
  final String date;
  const ReportDayScreen({super.key, required this.auth, required this.date});
  @override
  Widget build(BuildContext context) => RemotePage(
    auth: auth,
    title: date,
    route: '/newsletters',
    query: {'from': date, 'to': date},
    content: (ctx, r, _) => Column(
      children: [
        if ((r['newsletters'] as List).isEmpty)
          const EmptyState('No Data Found', 'No newsletters for this date.'),
        for (final n in r['newsletters'])
          NewsletterCard(
            auth: auth,
            n: n,
            onTap: () => openPage(
              ctx,
              NewsletterDetailsScreen(auth: auth, id: number(n['id'])),
            ),
          ),
      ],
    ),
  );
}
