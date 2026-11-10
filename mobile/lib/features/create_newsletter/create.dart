import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:file_picker/file_picker.dart';

import '../../core/auth.dart';
import '../../models/newsletter.dart';
import '../../widgets/ui.dart';
import '../automation/job.dart';
import 'sections.dart';

const inputTemplate =
    'header:\n\nPick News:\n\nMore News:\n\nCategories:\nTOURISM NEWS:\n\nMEETING AND EVENT INDUSTRY NEWS:\n';

class CreateScreen extends StatefulWidget {
  final Auth auth;
  const CreateScreen({super.key, required this.auth});
  @override
  State<CreateScreen> createState() => _CreateState();
}

class _CreateState extends State<CreateScreen> {
  final input = TextEditingController(),
      subject = TextEditingController(),
      description = TextEditingController(),
      promotionUrl = TextEditingController(),
      promotionText = TextEditingController();
  DateTime date = DateTime.now();
  String edition = 'M';
  int step = 0;
  bool busy = false;
  String? error;
  List<String> warnings = [];
  List<ParsedArticle> articles = [];
  Map<String, dynamic>? banner;
  @override
  void dispose() {
    for (final c in [
      input,
      subject,
      description,
      promotionUrl,
      promotionText,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  String get issueDate =>
      '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  Future<void> run(Future<void> Function() fn) async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await fn();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  String structured() {
    String text = '', last = '';
    for (final a in articles) {
      final key = a.label;
      if (last != key) {
        text +=
            '\n${a.section == 'category' ? 'Categories:\n' : ''}${a.section == 'header' ? 'header' : a.label}:\n';
        last = key;
      }
      text += '${a.url}\n';
    }
    return text;
  }

  Future<void> parse(String text) async {
    final r = await widget.auth.api.request(
      '/automation/parse',
      data: {'text': text},
    );
    final errors = (r['errors'] as List).cast<String>();
    if (errors.isNotEmpty) throw Exception(errors.join('\n'));
    if (mounted) {
      setState(() {
        articles = (r['articles'] as List)
            .map((a) => ParsedArticle.fromJson(a))
            .toList();
        warnings = (r['warnings'] as List).cast<String>();
      });
    }
  }

  Future<void> chooseBanner() async {
    final source = await showModalBottomSheet<String>(
      context: context,
      builder: (c) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Gallery'),
              onTap: () => Navigator.pop(c, 'gallery'),
            ),
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined),
              title: const Text('Camera'),
              onTap: () => Navigator.pop(c, 'camera'),
            ),
            ListTile(
              leading: const Icon(Icons.attach_file),
              title: const Text('File'),
              onTap: () => Navigator.pop(c, 'file'),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;
    await run(() async {
      String? path;
      if (source == 'file') {
        final f = await FilePicker.pickFile(
          type: FileType.custom,
          allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
        );
        path = f?.path;
      } else {
        path = (await ImagePicker().pickImage(
          source: source == 'camera' ? ImageSource.camera : ImageSource.gallery,
        ))?.path;
      }
      if (path != null) {
        final result = await widget.auth.api.upload(
          path,
          newsletterDate: issueDate,
        );
        if (mounted) setState(() => banner = result);
      }
    });
  }

  Future<void> editUrl(int i) async {
    final c = TextEditingController(text: articles[i].url);
    final value = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Replace article URL'),
        content: TextField(
          controller: c,
          decoration: field('HTTPS article URL'),
          maxLines: 4,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, c.text),
            child: const Text('Replace'),
          ),
        ],
      ),
    );
    if (value != null && mounted) {
      setState(() => articles[i].url = value.trim());
    }
    c.dispose();
  }

  Widget review() => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Notice(
        '${articles.length} Articles Found\n${articles.where((a) => a.section == 'category').map((a) => a.categoryId).toSet().length} categories',
        success: true,
      ),
      const Text(
        'Review links now. Titles and images are fetched by the server after submission.',
      ),
      if (warnings.isNotEmpty) Notice(warnings.join('\n')),
      for (final section in ['header', 'pick', 'more', 'category']) ...[
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 14),
          child: Text(
            {
              'header': 'Header Story',
              'pick': 'Pick News',
              'more': 'More News',
              'category': 'Categories',
            }[section]!,
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
          ),
        ),
        ...articles.where((a) => a.section == section).map((a) {
          final i = articles.indexOf(a);
          return Card(
            child: ListTile(
              leading: Icon(
                section == 'category'
                    ? Icons.folder_outlined
                    : Icons.article_outlined,
                color: navy,
              ),
              title: Text(
                a.label,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                ),
              ),
              subtitle: Text(
                a.url,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
              ),
              onTap: () => editUrl(i),
              trailing: PopupMenuButton<String>(
                onSelected: (v) {
                  if (v == 'replace') {
                    editUrl(i);
                    return;
                  }
                  setState(() {
                    if (v == 'remove') {
                      articles.removeAt(i);
                    } else {
                      final j = v == 'up' ? i - 1 : i + 1;
                      if (j >= 0 &&
                          j < articles.length &&
                          articles[j].section == a.section &&
                          articles[j].categoryId == a.categoryId) {
                        articles[i] = articles[j];
                        articles[j] = a;
                      }
                    }
                  });
                },
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'replace', child: Text('Replace URL')),
                  PopupMenuItem(value: 'up', child: Text('Move up')),
                  PopupMenuItem(value: 'down', child: Text('Move down')),
                  PopupMenuItem(value: 'remove', child: Text('Remove')),
                ],
              ),
            ),
          );
        }),
      ],
    ],
  );
  Widget details() => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      const Text(
        'Promotion & Details',
        style: TextStyle(fontWeight: FontWeight.w800, fontSize: 20),
      ),
      const SizedBox(height: 20),
      const Text('Promotional Banner (Optional)'),
      const SizedBox(height: 12),
      if (banner != null) ...[
        ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: Image.network(
            banner!['url'],
            errorBuilder: (_, e, s) =>
                const Text('Banner uploaded; preview unavailable.'),
          ),
        ),
        TextButton.icon(
          onPressed: () => setState(() => banner = null),
          icon: const Icon(Icons.delete_outline),
          label: const Text('Remove banner'),
        ),
      ],
      OutlinedButton.icon(
        onPressed: busy ? null : chooseBanner,
        icon: const Icon(Icons.image_outlined),
        label: Text(banner == null ? 'Upload Banner' : 'Change Banner'),
      ),
      const SizedBox(height: 20),
      TextField(
        controller: promotionUrl,
        decoration: field('Promotion URL', icon: Icons.link),
        keyboardType: TextInputType.url,
      ),
      const SizedBox(height: 18),
      TextField(
        controller: promotionText,
        decoration: field('Promotion title (optional)'),
      ),
      const SizedBox(height: 18),
      TextField(
        controller: subject,
        decoration: field(
          'Email Title (optional — add later)',
          icon: Icons.mail_outline,
        ),
        maxLength: 250,
      ),
      const SizedBox(height: 18),
      TextField(
        controller: description,
        readOnly: true,
        decoration: field(
          'Hidden Preview Text',
          hint: 'Automatically taken from the first 16 words of Header content, followed by ...',
        ),
        maxLines: 5,
        maxLength: 5000,
      ),
    ],
  );
  Future<void> next() async {
    if (step == 0) {
      await parse(input.text);
      if (mounted) setState(() => step = 1);
    } else if (step == 1) {
      await parse(structured());
      if (mounted) setState(() => step = 2);
    } else {
      final r = await widget.auth.api.request(
        '/automation/jobs',
        data: {
          'newsletter_date': issueDate,
          'edition': edition,
          'text': structured(),
          'subject_title': subject.text,
          'hidden_text': description.text,
          'promotion_url': promotionUrl.text,
          'promotion_text': promotionText.text,
          if (banner != null) ...{
            'promotion_image': banner!['path'],
            'promotion_signature': banner!['signature'],
          },
        },
      );
      if (mounted) {
        await Navigator.pushReplacement(
          context,
          MaterialPageRoute<void>(
            builder: (_) =>
                JobScreen(auth: widget.auth, id: (r['id'] as num).toInt()),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text(
        'Create Newsletter',
        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
      ),
    ),
    body: SafeArea(
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 16, 18, 0),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: List.generate(
                4,
                (i) => Column(
                  children: [
                    CircleAvatar(
                      radius: 14,
                      backgroundColor: step == i
                          ? red
                          : const Color(0xffd6d6e4),
                      child: Text(
                        '${i + 1}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 13,
                        ),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      ['Input', 'Review', 'Details', 'Generate'][i],
                      style: TextStyle(
                        fontSize: 11,
                        color: i == step ? red : navy,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                if (error != null) Notice(error!),
                if (step == 0) ...[
                  OutlinedButton.icon(
                    onPressed: () => Navigator.pushReplacement(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => SectionDraftScreen(auth: widget.auth),
                      ),
                    ),
                    icon: const Icon(Icons.view_list_outlined),
                    label: const Text('Section by section · six steps'),
                  ),
                  const SizedBox(height: 12),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.calendar_month_outlined),
                    title: const Text('Newsletter Date'),
                    subtitle: Text(issueDate),
                    onTap: () async {
                      final d = await showDatePicker(
                        context: context,
                        initialDate: date,
                        firstDate: DateTime(2020),
                        lastDate: DateTime(2100),
                      );
                      if (d != null) setState(() => date = d);
                    },
                  ),
                  const SizedBox(height: 12),
                  SegmentedButton<String>(
                    segments: const [
                      ButtonSegment(
                        value: 'M',
                        label: Text('Morning'),
                        icon: Icon(Icons.wb_sunny_outlined),
                      ),
                      ButtonSegment(
                        value: 'E',
                        label: Text('Evening'),
                        icon: Icon(Icons.nightlight_outlined),
                      ),
                    ],
                    selected: {edition},
                    onSelectionChanged: (v) =>
                        setState(() => edition = v.first),
                  ),
                  const SizedBox(height: 24),
                  TextField(
                    controller: input,
                    decoration: field(
                      'Paste Newsletter Links',
                      hint: inputTemplate,
                    ),
                    maxLines: 14,
                    minLines: 10,
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    onPressed: () => setState(() => input.text = inputTemplate),
                    icon: const Icon(Icons.copy_outlined),
                    label: const Text('Use Template'),
                  ),
                ],
                if (step == 1) review(),
                if (step == 2) details(),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              children: [
                if (step > 0)
                  TextButton(
                    onPressed: busy ? null : () => setState(() => step--),
                    child: const Text('Back'),
                  ),
                PrimaryButton(
                  [
                    'Parse Links  →',
                    'Continue  →',
                    'Generate Newsletter  →',
                  ][step],
                  busy: busy,
                  onPressed: () => run(next),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}
