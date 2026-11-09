import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:file_picker/file_picker.dart';

import '../../core/api.dart';
import '../../core/auth.dart';
import '../../widgets/ui.dart';
import '../automation/job.dart';
import '../newsletter_preview/preview.dart';

class SectionDraftScreen extends StatefulWidget {
  final Auth auth;
  final int? id;
  const SectionDraftScreen({super.key, required this.auth, this.id});
  @override
  State<SectionDraftScreen> createState() => _SectionDraftState();
}

class _SectionDraftState extends State<SectionDraftScreen> {
  int? id;
  DateTime date = DateTime.now();
  String edition = 'M';
  bool busy = false, promotionChanged = false;
  String? error;
  Map<String, dynamic>? newsletter, banner;
  List<dynamic> categories = [];
  int? categoryId;
  final subject = TextEditingController(),
      promotionUrl = TextEditingController(),
      promotionText = TextEditingController();
  final links = {
    for (final s in ['header', 'pick', 'more', 'category'])
      s: TextEditingController(),
  };
  String get issueDate =>
      '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  Api get api => widget.auth.api;
  @override
  void initState() {
    super.initState();
    id = widget.id;
    initialize();
  }

  @override
  void dispose() {
    subject.dispose();
    promotionUrl.dispose();
    promotionText.dispose();
    for (final c in links.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> initialize() async {
    await run(() async {
      final r = await api.request('/categories');
      if (mounted) {
        setState(
          () => categories = (r['categories'] as List)
              .where((c) => int.parse('${c['active']}') == 1)
              .toList(),
        );
      }
      if (id != null) await load();
    });
  }

  Future<void> run(Future<void> Function() action) async {
    if (mounted) {
      setState(() {
        busy = true;
        error = null;
      });
    }
    try {
      await action();
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> load() async {
    final result = await api.request('/newsletters/$id');
    if (!mounted) return;
    final n = result['newsletter'] as Map<String, dynamic>;
    setState(() {
      newsletter = n;
      date = DateTime.parse(n['newsletter_date']);
      edition = n['edition'];
      subject.text = n['subject_title'] ?? '';
      promotionUrl.text = n['promotion_url'] ?? '';
      promotionText.text = n['promotion_text'] ?? '';
    });
    links['header']!.text = n['header_url'] ?? '';
    links['pick']!.text = (n['pick_news'] as List)
        .map((p) => p['url'])
        .join('\n');
    links['more']!.text = (n['more_news'] as List)
        .map((p) => p['url'])
        .join('\n');
    links['category']!.text = (n['categories'] as List)
        .map(
          (c) =>
              '${c['category_name']}:\n${(c['items'] as List).map((p) => p['url']).join('\n')}',
        )
        .join('\n\n');
  }

  Future<bool> save() async {
    try {
      final result = await api.request(
        '/newsletters/draft',
        data: {
          if (id != null) 'id': id,
          'newsletter_date': issueDate,
          'edition': edition,
          'subject_title': subject.text,
          'promotion_url': promotionUrl.text,
          'promotion_text': promotionText.text,
          if (promotionChanged) ...{
            'promotion_image': banner?['path'] ?? '',
            'promotion_url': promotionUrl.text,
            'promotion_text': promotionText.text,
            if (banner != null) 'promotion_signature': banner!['signature'],
          },
        },
      );
      if (mounted) {
        setState(() {
          id = int.parse('${result['id']}');
          newsletter = result['newsletter'];
          promotionChanged = false;
          banner = null;
        });
      }
      return true;
    } on ApiException catch (e) {
      if (e.existingId == null || !mounted) rethrow;
      final open = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('This edition already exists'),
          content: const Text(
            'Open its existing draft to continue working section by section.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Open draft'),
            ),
          ],
        ),
      );
      if (open == true) {
        id = e.existingId;
        await load();
      }
      return false;
    }
  }

  Future<void> importSection(String section) async {
    await run(() async {
      final input = {
        'input_mode': 'section',
        'section': section,
        'text': links[section]!.text,
        if (section == 'category' && categoryId != null)
          'category_id': categoryId,
      };
      final parsed = await api.request('/automation/parse', data: input);
      if ((parsed['errors'] as List).isNotEmpty) {
        throw Exception((parsed['errors'] as List).join('\n'));
      }
      if (!await save() || !mounted) return;
      final created = await api.request(
        '/automation/jobs',
        data: {
          ...input,
          'newsletter_date': issueDate,
          'edition': edition,
          'target_id': id,
        },
      );
      if (!mounted) return;
      await Navigator.push(
        context,
        MaterialPageRoute<void>(
          builder: (_) => JobScreen(
            auth: widget.auth,
            id: int.parse('${created['id']}'),
            returnToEditor: true,
          ),
        ),
      );
      if (mounted) await load();
    });
  }

  Future<void> pickBanner() async {
    final source = await showModalBottomSheet<String>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final option in ['Gallery', 'Camera', 'File'])
              ListTile(
                title: Text(option),
                onTap: () => Navigator.pop(ctx, option),
              ),
          ],
        ),
      ),
    );
    if (source == null) return;
    await run(() async {
      String? path;
      if (source == 'File') {
        path = (await FilePicker.pickFile(
          type: FileType.custom,
          allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
        ))?.path;
      } else {
        path = (await ImagePicker().pickImage(
          source: source == 'Camera' ? ImageSource.camera : ImageSource.gallery,
        ))?.path;
      }
      if (path != null) {
        final result = await api.upload(path);
        if (mounted) {
          setState(() {
            banner = result;
            promotionChanged = true;
          });
        }
      }
    });
  }

  Widget basic() => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      const Text(
        'Save each section as it arrives',
        style: TextStyle(fontSize: 21, fontWeight: FontWeight.bold),
      ),
      const SizedBox(height: 12),
      const Text(
        'You can start with More News or Categories, then add Header and Pick News later. The same date and edition use one draft.',
      ),
      ListTile(
        contentPadding: EdgeInsets.zero,
        leading: const Icon(Icons.calendar_month_outlined),
        title: Text(issueDate),
        subtitle: Text(id == null ? 'Choose newsletter date' : 'Draft #$id'),
        onTap: id != null
            ? null
            : () async {
                final d = await showDatePicker(
                  context: context,
                  initialDate: date,
                  firstDate: DateTime(2020),
                  lastDate: DateTime(2100),
                );
                if (d != null) setState(() => date = d);
              },
      ),
      SegmentedButton<String>(
        segments: const [
          ButtonSegment(value: 'M', label: Text('Morning')),
          ButtonSegment(value: 'E', label: Text('Evening')),
        ],
        selected: {edition},
        onSelectionChanged: id != null
            ? null
            : (v) => setState(() => edition = v.first),
      ),
      const SizedBox(height: 22),
      TextField(
        controller: subject,
        decoration: field('Email Title (optional — add later)'),
        maxLength: 250,
      ),
      const SizedBox(height: 12),
      InputDecorator(
        decoration: field('Hidden Preview Text'),
        child: Text(
          (newsletter?['hidden_text'] as String? ?? '').isEmpty
              ? 'Automatically filled from the first 16 words of Header content, followed by ...'
              : newsletter!['hidden_text'],
        ),
      ),
      const SizedBox(height: 22),
      PrimaryButton(
        'Save Basic Details',
        busy: busy,
        onPressed: () => run(() async {
          if (await save() && mounted) {
            ScaffoldMessenger.of(context)
                .showSnackBar(const SnackBar(content: Text('Draft saved.')));
          }
        }),
      ),
    ],
  );
  Widget section(String scope, String label) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        label,
        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 22),
      ),
      const SizedBox(height: 12),
      const Text(
        'Import this section only. Other sections stay in your draft.',
      ),
      const SizedBox(height: 18),
      if (scope == 'category') ...[
        DropdownButtonFormField<int>(
          initialValue: categoryId,
          decoration: field('Category'),
          items: [
            const DropdownMenuItem<int>(
              value: null,
              child: Text('Several categories with headings'),
            ),
            ...categories.map(
              (c) => DropdownMenuItem<int>(
                value: int.parse('${c['id']}'),
                child: Text(
                  c['category_name'],
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ),
          ],
          isExpanded: true,
          onChanged: (value) => setState(() {
            categoryId = value;
            links['category']!.clear();
          }),
        ),
        const SizedBox(height: 16),
      ],
      TextField(
        controller: links[scope],
        decoration: field(
          'Paste $label links',
          hint: scope == 'category' && categoryId == null
              ? 'TOURISM NEWS:\nhttps://...'
              : 'https://www.travelandtourworld.com/news/article/...',
        ),
        minLines: 6,
        maxLines: 12,
      ),
      const SizedBox(height: 18),
      PrimaryButton(
        'Import / Refresh $label',
        busy: busy,
        onPressed: () => importSection(scope),
      ),
      if (scope == 'header')
        const Notice(
          'Uses the original article heading, 35 opening content words for the description, and 16 for Hidden Preview Text.',
        ),
      if (scope == 'pick')
        const Notice(
          'Each description uses the first 16 words of its article content, followed by ...',
        ),
      if (newsletter != null) ...[
        const SizedBox(height: 22),
        const Text(
          'Saved content',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        if (scope == 'header')
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(newsletter!['header_title'] ?? ''),
            subtitle: Text(newsletter!['header_description'] ?? ''),
          ),
        if (scope == 'pick' || scope == 'more')
          ...(newsletter![scope == 'pick' ? 'pick_news' : 'more_news'] as List)
              .map(
                (p) => Card(
                  child: ListTile(
                    title: Text(p['title']),
                    subtitle: Text(p['description'] ?? p['url']),
                  ),
                ),
              ),
        if (scope == 'category')
          ...(newsletter!['categories'] as List).map(
            (c) => Card(
              child: ListTile(
                title: Text(c['category_name']),
                subtitle: Text(
                  (c['items'] as List).map((p) => p['title']).join('\n'),
                ),
              ),
            ),
          ),
      ],
    ],
  );
  Widget promotion() {
    String? image = banner?['url'];
    if (image == null &&
        !promotionChanged &&
        (newsletter?['promotion_enabled'].toString() == '1') &&
        newsletter?['promotion_image'] != null) {
      final path = newsletter!['promotion_image'] as String;
      image = Uri.tryParse(path)?.hasScheme == true
          ? path
          : Uri.parse(api.base).resolve('../$path').toString();
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Promotion (optional)',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 22),
        ),
        const SizedBox(height: 16),
        if (image != null)
          Image.network(
            image,
            errorBuilder: (_, e, s) =>
                const Text('Banner preview unavailable.'),
          ),
        OutlinedButton.icon(
          onPressed: busy ? null : pickBanner,
          icon: const Icon(Icons.image_outlined),
          label: const Text('Gallery / Camera / File'),
        ),
        TextButton(
          onPressed: () => setState(() {
            banner = null;
            promotionChanged = true;
          }),
          child: const Text('Remove promotion'),
        ),
        TextField(
          controller: promotionUrl,
          decoration: field('Promotion URL'),
          onChanged: (_) {
            if (banner != null) promotionChanged = true;
          },
        ),
        const SizedBox(height: 16),
        TextField(
          controller: promotionText,
          decoration: field('Promotion title (optional)'),
          onChanged: (_) {
            if (banner != null) promotionChanged = true;
          },
        ),
        const SizedBox(height: 22),
        PrimaryButton(
          'Save Promotion',
          busy: busy,
          onPressed: () => run(() async {
            if (await save() && mounted) {
              ScaffoldMessenger.of(
                context,
              ).showSnackBar(const SnackBar(content: Text('Promotion saved.')));
            }
          }),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) => DefaultTabController(
    length: 6,
    child: Scaffold(
      appBar: AppBar(
        title: const Text(
          'Section by Section',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
        ),
        bottom: const TabBar(
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          tabs: [
            Tab(text: 'Basic'),
            Tab(text: 'Header'),
            Tab(text: 'Pick News'),
            Tab(text: 'More News'),
            Tab(text: 'Categories'),
            Tab(text: 'Promotion'),
          ],
        ),
        actions: [
          if (id != null)
            IconButton(
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute<void>(
                  builder: (_) => PreviewScreen(auth: widget.auth, id: id!),
                ),
              ),
              icon: const Icon(Icons.visibility_outlined),
            ),
        ],
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
            Expanded(
              child: TabBarView(
                children:
                    [
                          basic(),
                          section('header', 'Header'),
                          section('pick', 'Pick News'),
                          section('more', 'MTN / More News'),
                          section('category', 'Categories'),
                          promotion(),
                        ]
                        .map(
                          (child) => ListView(
                            padding: const EdgeInsets.all(20),
                            children: [child],
                          ),
                        )
                        .toList(),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}
