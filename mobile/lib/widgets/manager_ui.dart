import 'package:flutter/material.dart';

import '../core/auth.dart';
import 'ui.dart';

int number(dynamic value) => int.tryParse('$value') ?? 0;
String issueLabel(Map n) =>
    '${n['newsletter_date']} · ${n['edition'] == 'M' ? 'Morning' : 'Evening'}';

class StatusBadge extends StatelessWidget {
  final String label;
  const StatusBadge(this.label, {super.key});
  @override
  Widget build(BuildContext context) {
    final ok = ['Approved', 'Published', 'Completed', 'Active'].contains(label);
    final color = ok
        ? Colors.green
        : label == 'Failed'
        ? Colors.red
        : label == 'Draft'
        ? Colors.orange
        : blue;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .1),
        borderRadius: BorderRadius.circular(7),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: color,
        ),
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  final String title, message;
  final VoidCallback? retry;
  const EmptyState(this.title, this.message, {super.key, this.retry});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(28),
    child: Column(
      children: [
        Icon(
          retry == null ? Icons.inbox_outlined : Icons.cloud_off_outlined,
          size: 48,
          color: blue,
        ),
        const SizedBox(height: 16),
        Text(
          title,
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        const SizedBox(height: 8),
        Text(message, textAlign: TextAlign.center),
        if (retry != null)
          TextButton(onPressed: retry, child: const Text('Retry')),
      ],
    ),
  );
}

class RemotePage extends StatefulWidget {
  final Auth auth;
  final String title, route;
  final Map<String, String> query;
  final Widget Function(BuildContext, Map<String, dynamic>, VoidCallback)
  content;
  final List<Widget>? actions;
  const RemotePage({
    super.key,
    required this.auth,
    required this.title,
    required this.route,
    required this.content,
    this.query = const {},
    this.actions,
  });
  @override
  State<RemotePage> createState() => _RemotePageState();
}

class _RemotePageState extends State<RemotePage> {
  Map<String, dynamic>? data;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void didUpdateWidget(RemotePage old) {
    super.didUpdateWidget(old);
    if (old.route != widget.route ||
        old.query.toString() != widget.query.toString()) {
      load();
    }
  }

  Future<void> load() async {
    if (mounted) setState(() => error = null);
    try {
      final r = await widget.auth.api.request(
        widget.route,
        query: widget.query,
      );
      if (mounted) setState(() => data = r);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.title), actions: widget.actions),
    body: SafeArea(
      child: error != null
          ? EmptyState('Something went wrong', error!, retry: load)
          : data == null
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(18),
                children: [widget.content(context, data!, load)],
              ),
            ),
    ),
  );
}

Future<bool> confirmAction(
  BuildContext context,
  String title,
  String message,
) async =>
    await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Continue'),
          ),
        ],
      ),
    ) ??
    false;
Future<void> openPage(BuildContext context, Widget page) =>
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => page));

class NewsletterCard extends StatelessWidget {
  final Auth auth;
  final Map n;
  final VoidCallback onTap;
  const NewsletterCard({
    super.key,
    required this.auth,
    required this.n,
    required this.onTap,
  });
  @override
  Widget build(BuildContext context) => Card(
    child: ListTile(
      contentPadding: const EdgeInsets.all(12),
      leading: ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: SizedBox(
          width: 62,
          height: 62,
          child: n['header_image'] != null
              ? Image.network(
                  auth.api.imageUrl(n['header_image'], kind: 'header'),
                  fit: BoxFit.cover,
                  errorBuilder: (_, e, s) =>
                      const Icon(Icons.newspaper, color: blue),
                )
              : const Icon(Icons.newspaper, color: blue),
        ),
      ),
      title: Text(
        issueLabel(n),
        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '${n['subject_title'] ?? n['header_title'] ?? 'Untitled draft'}'
                    .isEmpty
                ? 'Untitled draft'
                : '${n['subject_title'] ?? n['header_title']}',
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 5),
          Wrap(
            spacing: 6,
            children: [
              StatusBadge(
                n['approved_at'] == null
                    ? 'Draft'
                    : n['publish_status'] == 'published'
                    ? 'Published'
                    : 'Approved',
              ),
              if (n['article_count'] != null)
                Text(
                  '${n['article_count']} articles',
                  style: const TextStyle(fontSize: 11),
                ),
            ],
          ),
        ],
      ),
      trailing: const Icon(Icons.chevron_right, size: 18),
      onTap: onTap,
    ),
  );
}
