import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../../core/auth.dart';
import '../../widgets/ui.dart';
import '../create_newsletter/sections.dart';

class PreviewScreen extends StatefulWidget {
  final Auth auth;
  final int id;
  const PreviewScreen({super.key, required this.auth, required this.id});
  @override
  State<PreviewScreen> createState() => _PreviewState();
}

class _PreviewState extends State<PreviewScreen> {
  late final WebViewController web;
  String? error;
  bool busy = true, approved = false;
  @override
  void initState() {
    super.initState();
    web = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.disabled)
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (r) {
            if (r.url == 'about:blank' || r.url.startsWith('data:text/html')) {
              return NavigationDecision.navigate;
            }
            return NavigationDecision.prevent;
          },
        ),
      );
    load();
  }

  Future<void> load() async {
    if (mounted) {
      setState(() {
        busy = true;
        error = null;
      });
    }
    try {
      final n = await widget.auth.api.request('/newsletters/${widget.id}');
      final response = await widget.auth.api.client
          .get(
            widget.auth.api.uri('/newsletters/${widget.id}/preview'),
            headers: widget.auth.api.headers,
          )
          .timeout(const Duration(seconds: 30));
      if (response.statusCode != 200) {
        widget.auth.api.decode(response.statusCode, response.body);
        throw Exception('Preview unavailable.');
      }
      await web.loadHtmlString(response.body);
      if (mounted) {
        setState(() => approved = n['newsletter']['approved_at'] != null);
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> approve() async {
    setState(() => busy = true);
    try {
      await widget.auth.api.request(
        '/newsletters/${widget.id}/approve',
        data: {},
      );
      if (mounted) {
        setState(() => approved = true);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Newsletter approved.')));
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> edit() async {
    try {
      final result = await widget.auth.api.request('/newsletters/${widget.id}');
      if (!mounted) return;
      final n = result['newsletter'] as Map<String, dynamic>;
      final fields = <String, TextEditingController>{
        for (final key in [
          'subject_title',
          'hidden_text',
          'header_title',
          'header_description',
        ])
          key: TextEditingController(text: n[key] ?? ''),
      };
      final picked = (n['pick_news'] as List)
          .map(
            (p) => {
              'id': p['id'],
              'title': TextEditingController(text: p['title']),
              'description': TextEditingController(text: p['description']),
            },
          )
          .toList();
      final save = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Edit Draft'),
          content: SizedBox(
            width: 450,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  for (final e in fields.entries)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: TextField(
                        controller: e.value,
                        decoration: field(
                          {
                            'subject_title': 'Email Subject',
                            'hidden_text': 'Hidden Preview Text',
                            'header_title': 'Header Title',
                            'header_description': 'Header Description',
                          }[e.key]!,
                        ),
                        maxLines:
                            e.key.contains('description') ||
                                e.key == 'hidden_text'
                            ? 4
                            : 2,
                      ),
                    ),
                  for (final p in picked) ...[
                    const Text('Pick News'),
                    TextField(
                      controller: p['title'] as TextEditingController,
                      decoration: field('Original title'),
                      maxLines: 3,
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: p['description'] as TextEditingController,
                      decoration: field('Description'),
                      maxLines: 4,
                    ),
                    const SizedBox(height: 18),
                  ],
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Save Draft'),
            ),
          ],
        ),
      );
      if (save == true) {
        await widget.auth.api.request(
          '/newsletters/${widget.id}',
          data: {
            for (final e in fields.entries) e.key: e.value.text,
            'pick_news': picked
                .map(
                  (p) => {
                    'id': p['id'],
                    'title': (p['title'] as TextEditingController).text,
                    'description':
                        (p['description'] as TextEditingController).text,
                  },
                )
                .toList(),
          },
        );
        await load();
      }
      for (final c in fields.values) {
        c.dispose();
      }
      for (final p in picked) {
        (p['title'] as TextEditingController).dispose();
        (p['description'] as TextEditingController).dispose();
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text(
        'Newsletter Preview',
        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
      ),
      actions: [IconButton(onPressed: load, icon: const Icon(Icons.refresh))],
    ),
    body: SafeArea(
      child: Column(
        children: [
          if (busy) const LinearProgressIndicator(),
          if (error != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18),
              child: Notice(error!),
            ),
          if (approved)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 18),
              child: Notice('✓ Approved', success: true),
            ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(18),
                child: WebViewWidget(controller: web),
              ),
            ),
          ),
          if (widget.auth.can('edit'))
            TextButton.icon(
              onPressed: busy
                  ? null
                  : () async {
                      await Navigator.push(
                        context,
                        MaterialPageRoute<void>(
                          builder: (_) => SectionDraftScreen(
                            auth: widget.auth,
                            id: widget.id,
                          ),
                        ),
                      );
                      if (mounted) await load();
                    },
              icon: const Icon(Icons.view_list_outlined),
              label: const Text('Continue section by section'),
            ),
          if (widget.auth.can('edit'))
            Padding(
              padding: const EdgeInsets.all(18),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: busy ? null : edit,
                      icon: const Icon(Icons.edit_outlined),
                      label: const Text('Edit Draft'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: PrimaryButton(
                      approved ? '✓ Approved' : '✓ Approve',
                      busy: busy,
                      onPressed: approved ? null : approve,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    ),
  );
}
