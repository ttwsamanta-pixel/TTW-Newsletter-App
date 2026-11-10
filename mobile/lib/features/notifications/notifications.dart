import 'package:flutter/material.dart';

import '../../core/auth.dart';
import '../../widgets/ui.dart';
import '../../widgets/manager_ui.dart';
import '../automation/job.dart';
import '../newsletters/details.dart';

class NotificationsScreen extends StatefulWidget {
  final Auth auth;
  const NotificationsScreen({super.key, required this.auth});
  @override
  State<NotificationsScreen> createState() => _NotificationsState();
}

class _NotificationsState extends State<NotificationsScreen> {
  bool unread = false;
  @override
  Widget build(BuildContext context) => RemotePage(
    auth: widget.auth,
    title: 'Notifications',
    route: '/notifications',
    content: (ctx, r, reload) {
      final rows = (r['notifications'] as List).where(
        (n) => !unread || n['read'] != true,
      );
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SegmentedButton<bool>(
            segments: [
              const ButtonSegment(value: false, label: Text('All')),
              ButtonSegment(
                value: true,
                label: Text('Unread (${r['unread']})'),
              ),
            ],
            selected: {unread},
            onSelectionChanged: (v) => setState(() => unread = v.first),
          ),
          TextButton(
            onPressed: () async {
              try {
                await widget.auth.api.request('/notifications/read', data: {});
                reload();
              } catch (e) {
                if (ctx.mounted) showFailure(ctx, e);
              }
            },
            child: const Text('Mark all as read'),
          ),
          if (rows.isEmpty)
            const EmptyState('You are up to date', 'No notifications to show.'),
          for (final n in rows)
            Card(
              child: ListTile(
                leading: Icon(
                  n['kind'] == 'success'
                      ? Icons.check_circle
                      : n['kind'] == 'warning'
                      ? Icons.warning_amber
                      : Icons.save_outlined,
                  color: n['kind'] == 'warning' ? Colors.orange : blue,
                ),
                title: Text(
                  n['title'],
                  style: TextStyle(
                    fontWeight: n['read'] == true
                        ? FontWeight.w500
                        : FontWeight.bold,
                  ),
                ),
                subtitle: Text('${n['message']}\n${n['time']}'),
                isThreeLine: true,
                trailing: n['read'] == true
                    ? null
                    : const Icon(Icons.circle, size: 8, color: blue),
                onTap: () async {
                  try {
                    await widget.auth.api.request(
                      '/notifications/read',
                      data: {'key': n['key']},
                    );
                    if (!ctx.mounted) return;
                    if (n['newsletter_id'] != null) {
                      await openPage(
                        ctx,
                        NewsletterDetailsScreen(
                          auth: widget.auth,
                          id: number(n['newsletter_id']),
                        ),
                      );
                    } else if (n['job_id'] != null) {
                      await openPage(
                        ctx,
                        JobScreen(auth: widget.auth, id: number(n['job_id'])),
                      );
                    }
                    reload();
                  } catch (e) {
                    if (ctx.mounted) showFailure(ctx, e);
                  }
                },
              ),
            ),
        ],
      );
    },
  );
}
