class Newsletter {
  final int id;
  final String date, edition, subject;
  final bool approved;
  Newsletter.fromJson(Map<String, dynamic> j)
    : id = int.parse('${j['id']}'),
      date = j['newsletter_date'] as String,
      edition = j['edition'] as String,
      subject = j['subject_title'] as String? ?? '',
      approved = j['approved_at'] != null;
}

class ParsedArticle {
  final String section;
  final int? categoryId;
  final String? categoryName;
  String url;
  ParsedArticle.fromJson(Map<String, dynamic> j)
    : section = j['section'] as String,
      categoryId = (j['category_id'] as num?)?.toInt(),
      categoryName = j['category_name'] as String?,
      url = j['url'] as String;
  String get label =>
      categoryName ??
      {
        'header': 'Header Story',
        'pick': 'Pick News',
        'more': 'More News',
      }[section] ??
      section;
}

class AutomationJob {
  final Map<String, dynamic> payload;
  final int id, total, completed;
  final int? newsletterId;
  final String status;
  final List<Map<String, dynamic>> articles;
  AutomationJob.fromJson(Map<String, dynamic> j)
    : payload = (j['payload'] as Map<String, dynamic>?) ?? {},
      id = int.parse('${j['id']}'),
      total = int.parse('${j['total']}'),
      completed = int.parse('${j['completed']}'),
      newsletterId = j['newsletter_id'] == null
          ? null
          : int.parse('${j['newsletter_id']}'),
      status = j['status'] as String,
      articles = (j['articles'] as List? ?? []).cast<Map<String, dynamic>>();
  bool get finished =>
      ['completed', 'completed_with_warnings', 'failed'].contains(status);
  double get progress => total == 0 ? 0 : completed / total;
  bool get isSectionImport => payload['input_mode'] == 'section';
}
