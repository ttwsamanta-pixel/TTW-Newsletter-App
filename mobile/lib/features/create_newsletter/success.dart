import 'package:flutter/material.dart';

import '../../core/auth.dart';
import '../../widgets/ui.dart';
import '../../widgets/manager_ui.dart';
import '../newsletters/details.dart';
import '../newsletter_preview/preview.dart';

class CreatedScreen extends StatelessWidget {
  final Auth auth;
  final int id;
  const CreatedScreen({super.key, required this.auth, required this.id});
  @override
  Widget build(BuildContext context) => RemotePage(
    auth: auth,
    title: 'Newsletter Created',
    route: '/newsletters/$id',
    content: (ctx, r, _) {
      final n = r['newsletter'] as Map;
      final picks = n['pick_news'] as List,
          more = n['more_news'] as List,
          categories = n['categories'] as List;
      final count =
          (n['header_title'] == '' ? 0 : 1) +
          picks.length +
          more.length +
          categories.fold<int>(
            0,
            (sum, c) => sum + (c['items'] as List).length,
          );
      final images =
          (n['header_image'] == null ? 0 : 1) +
          picks.where((p) => p['image'] != null).length;
      return Column(
        children: [
          const SizedBox(height: 24),
          const Icon(Icons.check_circle, color: Colors.green, size: 86),
          const SizedBox(height: 24),
          const Text(
            'Newsletter Created',
            style: TextStyle(
              fontWeight: FontWeight.w800,
              fontSize: 25,
              color: navy,
            ),
          ),
          const SizedBox(height: 18),
          Text(issueLabel(n)),
          const SizedBox(height: 20),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(22),
              child: Column(
                children: [
                  _row('$count Articles'),
                  _row('$images Images'),
                  _row('${categories.length} Categories'),
                ],
              ),
            ),
          ),
          const SizedBox(height: 24),
          PrimaryButton(
            'View Newsletter',
            onPressed: () => openPage(ctx, PreviewScreen(auth: auth, id: id)),
          ),
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: () =>
                openPage(ctx, NewsletterDetailsScreen(auth: auth, id: id)),
            child: const Text('Newsletter Details & Publishing'),
          ),
          TextButton(
            onPressed: () =>
                Navigator.of(ctx).popUntil((route) => route.isFirst),
            child: const Text('Back to Dashboard'),
          ),
        ],
      );
    },
  );
  Widget _row(String text) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 9),
    child: Row(
      children: [
        const Icon(Icons.check_circle_outline, color: Colors.green),
        const SizedBox(width: 12),
        Text(text),
      ],
    ),
  );
}
