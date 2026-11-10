import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api.dart';
import '../../core/auth.dart';
import '../../models/newsletter.dart';
import '../../widgets/ui.dart';
import '../newsletter_preview/preview.dart';
import '../create_newsletter/success.dart';

class JobScreen extends StatefulWidget {
  final Auth auth;
  final int id;
  final bool returnToEditor;
  const JobScreen({
    super.key,
    required this.auth,
    required this.id,
    this.returnToEditor = false,
  });
  @override
  State<JobScreen> createState() => _JobState();
}

class _JobState extends State<JobScreen> {
  AutomationJob? job;
  Timer? timer;
  String? error;
  bool busy = false, accepted = false;
  @override
  void initState() {
    super.initState();
    poll();
  }

  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  Future<void> poll() async {
    timer?.cancel();
    try {
      final r = AutomationJob.fromJson(
        await widget.auth.api.request('/automation/jobs/${widget.id}'),
      );
      if (!mounted) return;
      setState(() {
        job = r;
        error = null;
      });
      if (!r.finished) timer = Timer(const Duration(seconds: 3), poll);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    }
  }

  Future<void> preview(int id) async {
    await Navigator.push(
      context,
      MaterialPageRoute<void>(
        builder: (_) => PreviewScreen(auth: widget.auth, id: id),
      ),
    );
    if (mounted) poll();
  }

  Future<void> generate({bool replace = false}) async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final r = await widget.auth.api.request(
        '/automation/jobs/${widget.id}/generate',
        data: {'accept_warnings': accepted, 'replace_draft': replace},
      );
      if (mounted) {
        if (widget.returnToEditor) {
          Navigator.pop(context);
          return;
        }
        await Navigator.push(
          context,
          MaterialPageRoute<void>(
            builder: (_) => CreatedScreen(
              auth: widget.auth,
              id: int.parse('${r['newsletter_id']}'),
            ),
          ),
        );
      }
    } on ApiException catch (e) {
      if (e.existingId != null && mounted) {
        final choice = await showDialog<String>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Text('Newsletter already exists'),
            content: Text(
              job?.isSectionImport == true
                  ? 'Open the existing draft, update only the selected section, or cancel.'
                  : 'Open the existing issue, replace its unapproved draft, or cancel.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx, 'cancel'),
                child: const Text('Cancel'),
              ),
              TextButton(
                onPressed: () => Navigator.pop(ctx, 'open'),
                child: const Text('Open existing'),
              ),
              if (widget.auth.can('edit'))
                FilledButton(
                  onPressed: () => Navigator.pop(ctx, 'replace'),
                  child: Text(
                    job?.isSectionImport == true
                        ? 'Update section'
                        : 'Replace draft',
                  ),
                ),
            ],
          ),
        );
        if (choice == 'open' && mounted) {
          await preview(e.existingId!);
        }
        if (choice == 'replace' && !replace && mounted) {
          await generate(replace: true);
        } else if (choice == 'replace' && replace && mounted) {
          setState(
            () => error =
                'Approved issues cannot be replaced. Open the existing issue.',
          );
        }
      } else if (mounted) {
        setState(() => error = e.toString());
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> retry() async {
    setState(() => busy = true);
    try {
      await widget.auth.api.request(
        '/automation/jobs/${widget.id}/retry',
        data: {},
      );
      accepted = false;
      await poll();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final j = job;
    return Scaffold(
      appBar: AppBar(title: Text('Automation #${widget.id}')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Container(
              padding: const EdgeInsets.all(28),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(25),
                gradient: const LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [navy, Color(0xff703f87), navy],
                ),
              ),
              child: Column(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Brand(size: 65),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    j?.newsletterId != null
                        ? (j?.isSectionImport == true
                              ? 'Section Saved'
                              : 'Newsletter Draft Created')
                        : j?.finished == true
                        ? 'Review Newsletter Data'
                        : (j?.isSectionImport == true
                              ? 'Importing Section…'
                              : 'Creating Newsletter…'),
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 23,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 14),
                  const Text(
                    'Fetching original articles, processing images\nand preparing your newsletter.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.white),
                  ),
                  const SizedBox(height: 30),
                  SizedBox(
                    width: 126,
                    height: 126,
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        CircularProgressIndicator(
                          value: j?.progress,
                          strokeWidth: 11,
                          color: const Color(0xffd76ee7),
                          backgroundColor: Colors.white24,
                        ),
                        Center(
                          child: Text(
                            '${((j?.progress ?? 0) * 100).round()}%',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 30,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 22),
                  Text(
                    '${j?.completed ?? 0}/${j?.total ?? 0} articles · ${j?.status.replaceAll('_', ' ') ?? 'Loading'}',
                    style: const TextStyle(color: Colors.white),
                  ),
                ],
              ),
            ),
            if (error != null) ...[
              Notice(error!),
              TextButton(onPressed: poll, child: const Text('Refresh status')),
            ],
            if (j == null && error == null)
              const Padding(
                padding: EdgeInsets.all(30),
                child: Center(child: CircularProgressIndicator()),
              ),
            if (j != null) ...[
              const SizedBox(height: 20),
              ...j.articles.map((a) {
                final r = a['result'] as Map<String, dynamic>?;
                final warning = ['warning', 'failed'].contains(a['status']);
                return Card(
                  child: ExpansionTile(
                    leading: Icon(
                      warning
                          ? Icons.warning_amber
                          : a['status'] == 'completed'
                          ? Icons.check_circle
                          : Icons.radio_button_unchecked,
                      color: warning ? Colors.orange : Colors.green,
                    ),
                    title: Text(
                      r?['title'] ?? a['url'],
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    subtitle: Text('${a['section']} · ${a['status']}'),
                    children: [
                      Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            SelectableText(a['url']),
                            if (r != null) ...[
                              const SizedBox(height: 12),
                              Text(r['description'] ?? ''),
                              const SizedBox(height: 12),
                              Text(
                                '✓ URL valid\n${(r['title'] ?? '').isNotEmpty ? '✓' : '○'} Title found\n${(r['description'] ?? '').isNotEmpty ? '✓' : '⚠'} Description found\n${(r['featured_image'] ?? '').isNotEmpty ? '✓' : '○'} Featured image found',
                              ),
                              if (r['image'] != null)
                                const Text('✓ Newsletter image saved'),
                            ],
                            if (a['error_message'] != null)
                              Notice(a['error_message']),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              }),
              if (j.status == 'completed_with_warnings' &&
                  j.newsletterId == null)
                CheckboxListTile(
                  value: accepted,
                  onChanged: (v) => setState(() => accepted = v ?? false),
                  title: const Text(
                    'I reviewed the warnings and accept missing descriptions or placeholder images.',
                    style: TextStyle(fontSize: 13),
                  ),
                ),
              if (j.finished &&
                  j.status != 'completed' &&
                  j.newsletterId == null)
                OutlinedButton.icon(
                  onPressed: busy ? null : retry,
                  icon: const Icon(Icons.refresh),
                  label: const Text('Retry articles with problems'),
                ),
              const SizedBox(height: 16),
              if (j.newsletterId != null)
                PrimaryButton(
                  widget.returnToEditor
                      ? 'Return to section editor'
                      : 'Preview Newsletter  →',
                  onPressed: () => widget.returnToEditor
                      ? Navigator.pop(context)
                      : preview(j.newsletterId!),
                )
              else if (j.finished && j.status != 'failed')
                PrimaryButton(
                  j.isSectionImport
                      ? 'Save imported section'
                      : 'Generate Draft  →',
                  busy: busy,
                  onPressed: j.status == 'completed_with_warnings' && !accepted
                      ? null
                      : generate,
                )
              else if (j.status == 'failed')
                const Notice(
                  'Missing article titles prevent generation. Retry failed articles, or create a new job with corrected links.',
                ),
            ],
          ],
        ),
      ),
    );
  }
}
