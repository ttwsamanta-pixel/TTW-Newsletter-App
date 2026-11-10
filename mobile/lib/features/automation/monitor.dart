import 'package:flutter/material.dart';

import '../../core/auth.dart';
import '../../widgets/ui.dart';
import '../../widgets/manager_ui.dart';
import 'job.dart';

class AutomationScreen extends StatelessWidget {
  final Auth auth;
  const AutomationScreen({super.key, required this.auth});
  @override
  Widget build(BuildContext context) => RemotePage(
    auth: auth,
    title: 'Automation',
    route: '/automation/status',
    content: (ctx, r, reload) {
      final jobs = r['jobs'] as List;
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Row(
                children: [
                  Icon(
                    r['worker_recent'] == true
                        ? Icons.check_circle
                        : Icons.schedule,
                    color: r['worker_recent'] == true
                        ? Colors.green
                        : Colors.orange,
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          r['worker_recent'] == true
                              ? 'Worker recently observed'
                              : 'Worker status unconfirmed',
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                        Text(
                          'Last check: ${r['heartbeat']?['checked_at'] ?? 'No heartbeat received'}',
                          style: const TextStyle(fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            alignment: WrapAlignment.spaceAround,
            spacing: 15,
            children: [
              Text("Today: ${r['stats']?['today'] ?? 0}"),
              Text("Success: ${r['stats']?['success'] ?? 0}"),
              Text("Failed: ${r['stats']?['failed'] ?? 0}"),
            ],
          ),
          const SizedBox(height: 20),
          if (auth.user?['role'] == 'admin')
            PrimaryButton(
              'Run Now',
              onPressed: () async {
                try {
                  final result = await auth.api.request(
                    '/automation/run',
                    data: {},
                  );
                  if (ctx.mounted) {
                    ScaffoldMessenger.of(
                      ctx,
                    ).showSnackBar(SnackBar(content: Text(result['message'])));
                  }
                  reload();
                } catch (e) {
                  if (ctx.mounted) showFailure(ctx, e);
                }
              },
            ),
          const SizedBox(height: 20),
          const Text(
            'Recent Runs',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 19),
          ),
          if (jobs.isEmpty)
            const EmptyState(
              'No processing jobs',
              'Create a newsletter to start processing articles.',
            ),
          for (final j in jobs)
            Card(
              child: ListTile(
                title: Text('Job #${j['id']} · ${j['created_at']}'),
                subtitle: Text('${j['completed']}/${j['total']} articles'),
                trailing: StatusBadge('${j['status']}'.replaceAll('_', ' ')),
                onTap: () =>
                    openPage(ctx, JobScreen(auth: auth, id: number(j['id']))),
              ),
            ),
          TextButton.icon(
            onPressed: reload,
            icon: const Icon(Icons.refresh),
            label: const Text('Refresh Run History'),
          ),
        ],
      );
    },
  );
}
