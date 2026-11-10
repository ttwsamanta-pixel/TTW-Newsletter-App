import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:file_picker/file_picker.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../../core/auth.dart';
import '../../widgets/ui.dart';
import '../../widgets/manager_ui.dart';
import '../create_newsletter/sections.dart';
import '../newsletter_preview/preview.dart';

class NewsletterDetailsScreen extends StatefulWidget {
  final Auth auth;
  final int id;
  const NewsletterDetailsScreen({
    super.key,
    required this.auth,
    required this.id,
  });
  @override
  State<NewsletterDetailsScreen> createState() => _DetailsState();
}

class _DetailsState extends State<NewsletterDetailsScreen> {
  Map<String, dynamic>? n;
  String? error;
  bool busy = false;
  Auth get auth => widget.auth;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    await run(() async {
      final r = await auth.api.request('/newsletters/${widget.id}');
      if (mounted) setState(() => n = r['newsletter']);
    });
  }

  Future<void> run(Future<void> Function() task) async {
    if (mounted) {
      setState(() {
        busy = true;
        error = null;
      });
    }
    try {
      await task();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  void message(String text) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
    }
  }

  Future<void> download({bool copy = false}) async {
    await run(() async {
      final r = await auth.api.client
          .get(
            auth.api.uri('/newsletters/${widget.id}/download-html'),
            headers: auth.api.headers,
          )
          .timeout(const Duration(seconds: 30));
      if (r.statusCode != 200) {
        auth.api.decode(r.statusCode, r.body);
        return;
      }
      if (copy) {
        await Clipboard.setData(ClipboardData(text: r.body));
        message('HTML copied.');
      } else {
        final date = DateTime.parse(n!['newsletter_date']);
        const months = [
          'Jan',
          'Feb',
          'Mar',
          'Apr',
          'May',
          'Jun',
          'Jul',
          'Aug',
          'Sep',
          'Oct',
          'Nov',
          'Dec',
        ];
        final filename =
            'TTW_newsletter_${date.day.toString().padLeft(2, '0')}${months[date.month - 1]}${date.year}${n!['edition']}.html';
        final saved = await FilePicker.saveFile(
          fileName: filename,
          bytes: Uint8List.fromList(utf8.encode(r.body)),
          mimeType: 'text/html',
        );
        if (saved != null) message('HTML saved.');
      }
    });
  }

  Future<void> publish({bool replace = false}) async {
    if (!await confirmAction(
      context,
      replace ? 'Replace Published HTML?' : 'Publish HTML?',
      replace
          ? 'Replace the existing file on the main website with this approved version?'
          : 'Upload this approved newsletter HTML to the main website?',
    )) {
      return;
    }
    await run(() async {
      final r = await auth.api.request(
        '/newsletters/${widget.id}/publish-html',
        data: {'replace': replace},
        timeout: const Duration(seconds: 75),
      );
      if (mounted) setState(() => n!['publication'] = r['publication']);
      message('HTML Published.');
    });
  }

  Future<void> edit() async {
    if (n!['approved_at'] != null) {
      if (!await confirmAction(
        context,
        'Reopen as Draft?',
        'Editing requires approval again before publishing. The existing published HTML stays online.',
      )) {
        return;
      }
      await auth.api.request('/newsletters/${widget.id}/reopen', data: {});
    }
    if (!mounted) return;
    await openPage(context, SectionDraftScreen(auth: auth, id: widget.id));
    await load();
  }

  @override
  Widget build(BuildContext context) {
    final p = n?['publication'] as Map? ?? {};
    final url = p['public_url'] as String?;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Newsletter Details'),
        actions: [IconButton(onPressed: load, icon: const Icon(Icons.refresh))],
      ),
      body: SafeArea(
        child: n == null
            ? error != null
                  ? EmptyState('Unable to load', error!, retry: load)
                  : const Center(child: CircularProgressIndicator())
            : ListView(
                padding: const EdgeInsets.all(18),
                children: [
                  if (busy) const LinearProgressIndicator(),
                  if (error != null) Notice(error!),
                  if (n!['header_image'] != null)
                    ClipRRect(
                      borderRadius: BorderRadius.circular(16),
                      child: Image.network(
                        auth.api.imageUrl(n!['header_image'], kind: 'header'),
                        height: 180,
                        fit: BoxFit.cover,
                        errorBuilder: (_, e, s) => const SizedBox(
                          height: 80,
                          child: Icon(Icons.image_outlined),
                        ),
                      ),
                    ),
                  const SizedBox(height: 18),
                  Text(
                    '${n!['subject_title'] ?? ''}'.isEmpty
                        ? 'Untitled draft'
                        : n!['subject_title'],
                    style: const TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      color: navy,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        children: [
                          _row('Date', n!['newsletter_date']),
                          _row(
                            'Edition',
                            n!['edition'] == 'M' ? 'Morning' : 'Evening',
                          ),
                          _row(
                            'Status',
                            n!['approved_at'] == null ? 'Draft' : 'Approved',
                          ),
                          _row(
                            'Articles',
                            '${(n!['pick_news'] as List).length + (n!['more_news'] as List).length + (n!['categories'] as List).fold<int>(0, (sum, c) => sum + (c['items'] as List).length) + (n!['header_title'] == '' ? 0 : 1)}',
                          ),
                          _row(
                            'Categories',
                            '${(n!['categories'] as List).length}',
                          ),
                          _row('HTML', p['status'] ?? 'unpublished'),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  _action(
                    'View Newsletter',
                    Icons.visibility_outlined,
                    () => openPage(
                      context,
                      PreviewScreen(auth: auth, id: widget.id),
                    ),
                  ),
                  if (auth.can('edit'))
                    _action(
                      'Edit / Resume Draft',
                      Icons.edit_outlined,
                      () => run(edit),
                    ),
                  if (auth.can('download')) ...[
                    _action('Download HTML', Icons.download_outlined, download),
                    _action(
                      'Copy HTML',
                      Icons.copy_outlined,
                      () => download(copy: true),
                    ),
                  ],
                  if (auth.can('edit') && auth.can('download')) ...[
                    _action(
                      p['status'] == 'failed'
                          ? 'Retry Publish HTML'
                          : 'Publish HTML',
                      Icons.cloud_upload_outlined,
                      n!['approved_at'] == null ? null : publish,
                    ),
                    if (auth.user?['role'] == 'admin' && url != null)
                      _action(
                        'Replace Published HTML',
                        Icons.sync,
                        () => publish(replace: true),
                      ),
                  ],
                  if (n!['approved_at'] == null)
                    const Notice(
                      'Add Email Title and Header news, then approve in Preview before publishing.',
                    ),
                  if (url != null) ...[
                    const SizedBox(height: 14),
                    SelectableText(url, style: const TextStyle(color: blue)),
                    _action(
                      'View Published HTML',
                      Icons.open_in_new,
                      () => openPage(context, PublicHtmlScreen(url: url)),
                    ),
                    _action('Copy URL', Icons.link, () async {
                      await Clipboard.setData(ClipboardData(text: url));
                      message('URL copied.');
                    }),
                  ],
                  if (auth.can('delete'))
                    _action(
                      'Delete Newsletter',
                      Icons.delete_outline,
                      () async {
                        final navigator = Navigator.of(context);
                        if (!await confirmAction(
                          context,
                          'Delete Newsletter?',
                          'This removes the database newsletter. Published HTML and image files are retained.',
                        )) {
                          return;
                        }
                        await run(() async {
                          await auth.api.request(
                            '/newsletters/${widget.id}/delete',
                            data: {},
                          );
                          if (navigator.mounted) navigator.pop();
                        });
                      },
                      danger: true,
                    ),
                ],
              ),
      ),
    );
  }

  Widget _row(String key, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 7),
    child: Row(
      children: [
        Expanded(child: Text(key)),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
      ],
    ),
  );
  Widget _action(
    String label,
    IconData icon,
    VoidCallback? action, {
    bool danger = false,
  }) => Card(
    child: ListTile(
      enabled: !busy && action != null,
      leading: Icon(icon, color: danger ? Colors.red : blue),
      title: Text(label),
      trailing: const Icon(Icons.chevron_right, size: 18),
      onTap: busy ? null : action,
    ),
  );
}

class PublicHtmlScreen extends StatefulWidget {
  final String url;
  const PublicHtmlScreen({super.key, required this.url});
  @override
  State<PublicHtmlScreen> createState() => _PublicHtmlState();
}

class _PublicHtmlState extends State<PublicHtmlScreen> {
  late WebViewController web;
  @override
  void initState() {
    super.initState();
    web = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.disabled)
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (r) => r.url == widget.url
              ? NavigationDecision.navigate
              : NavigationDecision.prevent,
        ),
      )
      ..loadRequest(Uri.parse(widget.url));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Published Newsletter')),
    body: WebViewWidget(controller: web),
  );
}
