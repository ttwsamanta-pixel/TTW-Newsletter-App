import 'package:flutter/material.dart';

import '../../core/auth.dart';
import '../../widgets/ui.dart';
import '../../widgets/manager_ui.dart';

class ArticleEditorScreen extends StatefulWidget {
  final Auth auth;
  final int id;
  final String section;
  final int? categoryId;
  const ArticleEditorScreen({
    super.key,
    required this.auth,
    required this.id,
    required this.section,
    this.categoryId,
  });
  @override
  State<ArticleEditorScreen> createState() => _ArticleEditorState();
}

class _ArticleEditorState extends State<ArticleEditorScreen> {
  List<Map<String, dynamic>> items = [];
  bool busy = true;
  String? error;
  final title = TextEditingController(), description = TextEditingController();
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    title.dispose();
    description.dispose();
    super.dispose();
  }

  Future<void> load() async {
    try {
      final r = await widget.auth.api.request('/newsletters/${widget.id}');
      final n = r['newsletter'] as Map;
      if (widget.section == 'header') {
        title.text = n['header_title'] ?? '';
        description.text = n['header_description'] ?? '';
      } else {
        final List list = widget.section == 'category'
            ? (n['categories'] as List).firstWhere(
                (c) => number(c['id']) == widget.categoryId,
              )['items']
            : n[widget.section == 'pick' ? 'pick_news' : 'more_news'];
        items = list.map((p) => Map<String, dynamic>.from(p)).toList();
      }
    } catch (e) {
      error = e.toString();
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> edit(int index) async {
    final p = items[index];
    final t = TextEditingController(text: p['title']),
        d = TextEditingController(text: p['description'] ?? '');
    final result = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Edit Article'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: t,
                decoration: field('Original Title'),
                maxLines: 4,
              ),
              if (widget.section == 'pick') ...[
                const SizedBox(height: 18),
                TextField(
                  controller: d,
                  decoration: field('Description'),
                  maxLines: 5,
                ),
              ],
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Apply'),
          ),
        ],
      ),
    );
    if (result == true && mounted) {
      setState(() {
        items[index]['title'] = t.text;
        items[index]['description'] = d.text;
      });
    }
    t.dispose();
    d.dispose();
  }

  Future<void> save() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.auth.api.request(
        '/newsletters/${widget.id}/section',
        data: {
          'section': widget.section,
          if (widget.categoryId != null) 'category_id': widget.categoryId,
          if (widget.section == 'header') ...{
            'header_title': title.text,
            'header_description': description.text,
          } else
            'items': items
                .map(
                  (p) => {
                    'id': p['id'],
                    'title': p['title'],
                    'description': p['description'] ?? '',
                  },
                )
                .toList(),
        },
      );
      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Edit Section')),
    body: SafeArea(
      child: Column(
        children: [
          if (busy) const LinearProgressIndicator(),
          if (error != null) Notice(error!),
          if (widget.section == 'header')
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(22),
                children: [
                  TextField(
                    controller: title,
                    decoration: field('Header Title'),
                    maxLines: 4,
                  ),
                  const SizedBox(height: 22),
                  TextField(
                    controller: description,
                    decoration: field('Header Description'),
                    maxLines: 7,
                  ),
                ],
              ),
            )
          else
            Expanded(
              child: ReorderableListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: items.length,
                onReorderItem: (oldIndex, newIndex) {
                  setState(() {
                    items.insert(newIndex, items.removeAt(oldIndex));
                  });
                },
                itemBuilder: (ctx, i) {
                  final p = items[i];
                  return Card(
                    key: ValueKey(p['id']),
                    child: ListTile(
                      leading: p['image'] != null
                          ? ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: Image.network(
                                widget.auth.api.imageUrl(
                                  p['image'],
                                  kind: 'pick',
                                ),
                                width: 54,
                                height: 54,
                                fit: BoxFit.cover,
                                errorBuilder: (_, e, s) =>
                                    const Icon(Icons.image_outlined),
                              ),
                            )
                          : const Icon(Icons.drag_indicator),
                      title: Text(
                        p['title'],
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                      ),
                      subtitle: Text(
                        p['description'] ?? p['url'],
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      onTap: () => edit(i),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          IconButton(
                            tooltip: 'Remove article',
                            onPressed: () => setState(() => items.removeAt(i)),
                            icon: const Icon(
                              Icons.delete_outline,
                              color: Colors.red,
                            ),
                          ),
                          ReorderableDragStartListener(
                            index: i,
                            child: const Icon(Icons.drag_handle),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
          Padding(
            padding: const EdgeInsets.all(20),
            child: PrimaryButton('Save Section', busy: busy, onPressed: save),
          ),
        ],
      ),
    ),
  );
}
