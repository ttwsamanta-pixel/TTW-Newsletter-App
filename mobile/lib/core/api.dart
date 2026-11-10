import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

class ApiException implements Exception {
  final String message;
  final int status;
  final int? existingId;
  ApiException(this.message, [this.status = 0, this.existingId]);
  @override
  String toString() => message;
}

class Api {
  static const baseUrl = String.fromEnvironment(
    'API_URL',
    defaultValue: 'https://newsletter.travelandtourworld.com/New/api/index.php',
  );
  final http.Client client;
  final String base;
  String? token;
  VoidCallback? onExpired;
  Api({http.Client? client, this.base = baseUrl})
    : client = client ?? http.Client() {
    if (Uri.parse(base).scheme != 'https') {
      throw ArgumentError('API_URL must use HTTPS.');
    }
  }
  Uri uri(String route, [Map<String, String> query = const {}]) =>
      Uri.parse(base).replace(queryParameters: {'route': route, ...query});

  /// Uploaded images use the server's New -> original uploads -> placeholder resolver.
  String imageUrl(String path, {String kind = 'promotion'}) {
    final value = Uri.tryParse(path);
    final app = Uri.parse(base).resolve('../');
    if (value != null && value.hasScheme && value.host != app.host) return path;
    if (value != null && value.path.endsWith('/image.php')) return path;
    return app
        .resolve('image.php')
        .replace(queryParameters: {'path': path, 'kind': kind})
        .toString();
  }

  Map<String, String> get headers => {
    'Accept': 'application/json',
    if (token != null) 'Authorization': 'Bearer $token',
  };
  Future<Map<String, dynamic>> request(
    String route, {
    Map<String, dynamic>? data,
    Map<String, String> query = const {},
    Duration timeout = const Duration(seconds: 30),
  }) async {
    try {
      final response =
          await (data == null
                  ? client.get(uri(route, query), headers: headers)
                  : client.post(
                      uri(route),
                      headers: {...headers, 'Content-Type': 'application/json'},
                      body: jsonEncode(data),
                    ))
              .timeout(timeout);
      return decode(response.statusCode, response.body);
    } on TimeoutException {
      throw ApiException('The server took too long. Try again.');
    } on SocketException {
      throw ApiException('No connection. Check your network and try again.');
    } on http.ClientException {
      throw ApiException('Cannot reach the server. Check your connection.');
    }
  }

  Map<String, dynamic> decode(int status, String body) {
    Map<String, dynamic> result;
    try {
      result = jsonDecode(body) as Map<String, dynamic>;
    } catch (_) {
      throw ApiException('The server returned an unexpected response.', status);
    }
    if (status >= 400) {
      if (status == 401 && token != null) onExpired?.call();
      throw ApiException(
        result['error']?.toString() ?? 'Request failed.',
        status,
        (result['existing_id'] as num?)?.toInt(),
      );
    }
    return result;
  }

  Future<Map<String, dynamic>> upload(
    String path, {
    String route = '/uploads/promotion',
    String fileKey = 'banner',
  }) async {
    try {
      final request = http.MultipartRequest('POST', uri(route));
      request.headers.addAll(headers);
      request.files.add(await http.MultipartFile.fromPath(fileKey, path));
      final response = await client
          .send(request)
          .timeout(const Duration(seconds: 45));
      return decode(response.statusCode, await response.stream.bytesToString());
    } on TimeoutException {
      throw ApiException('Upload timed out. Try again.');
    } on IOException {
      throw ApiException('Could not read or upload the image.');
    } on http.ClientException {
      throw ApiException('Upload failed. Check your connection.');
    }
  }
}
