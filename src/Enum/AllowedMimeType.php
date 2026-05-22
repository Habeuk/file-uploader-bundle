<?php
namespace Habeuk\FileUploaderBundle\Enum;

enum AllowedMimeType: string {

  case IMAGE_JPEG = 'image/jpeg';

  case IMAGE_PNG = 'image/png';

  case IMAGE_GIF = 'image/gif';

  case IMAGE_WEBP = 'image/webp';

  case IMAGE_SVG = 'image/svg+xml';

  case IMAGE_BMP = 'image/bmp';

  // Documents
  case PDF = 'application/pdf';

  case DOC = 'application/msword';

  case DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

  case XLS = 'application/vnd.ms-excel';

  case XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

  case PPT = 'application/vnd.ms-powerpoint';

  case PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

  case ODT = 'application/vnd.oasis.opendocument.text';

  case ODS = 'application/vnd.oasis.opendocument.spreadsheet';

  // Archives
  case ZIP = 'application/zip';

  case RAR = 'application/vnd.rar';

  case GZIP = 'application/gzip';

  case TAR = 'application/x-tar';

  case SEVEN_ZIP = 'application/x-7z-compressed';

  // Texte et données
  case CSV = 'text/csv';

  case PLAIN_TEXT = 'text/plain';

  case JSON = 'application/json';

  case XML = 'application/xml';

  case YAML = 'application/x-yaml';

  // Audio
  case MP3 = 'audio/mpeg';

  case WAV = 'audio/wav';

  case OGG_AUDIO = 'audio/ogg';

  case FLAC = 'audio/flac';

  // Vidéo
  case MP4 = 'video/mp4';

  case WEBM = 'video/webm';

  case AVI = 'video/x-msvideo';

  case QUICKTIME = 'video/quicktime';

  public function getLabel(): string {
    return match ($this) {
      // Images
      self::IMAGE_JPEG => 'Image JPEG',
      self::IMAGE_PNG => 'Image PNG',
      self::IMAGE_GIF => 'Image GIF',
      self::IMAGE_WEBP => 'Image WebP',
      self::IMAGE_SVG => 'Image SVG',
      self::IMAGE_BMP => 'Image BMP',

      // Documents
      self::PDF => 'Document PDF',
      self::DOC => 'Document Word (DOC)',
      self::DOCX => 'Document Word (DOCX)',
      self::XLS => 'Feuille Excel (XLS)',
      self::XLSX => 'Feuille Excel (XLSX)',
      self::PPT => 'Présentation PowerPoint (PPT)',
      self::PPTX => 'Présentation PowerPoint (PPTX)',
      self::ODT => 'Document OpenOffice Text',
      self::ODS => 'Feuille OpenOffice Calc',

      // Archives
      self::ZIP => 'Archive ZIP',
      self::RAR => 'Archive RAR',
      self::GZIP => 'Archive GZip',
      self::TAR => 'Archive TAR',
      self::SEVEN_ZIP => 'Archive 7-Zip',

      // Texte et données
      self::CSV => 'Fichier CSV',
      self::PLAIN_TEXT => 'Fichier texte',
      self::JSON => 'Fichier JSON',
      self::XML => 'Fichier XML',
      self::YAML => 'Fichier YAML',

      // Audio
      self::MP3 => 'Audio MP3',
      self::WAV => 'Audio WAV',
      self::OGG_AUDIO => 'Audio OGG',
      self::FLAC => 'Audio FLAC',

      // Vidéo
      self::MP4 => 'Vidéo MP4',
      self::WEBM => 'Vidéo WebM',
      self::AVI => 'Vidéo AVI',
      self::QUICKTIME => 'Vidéo QuickTime'
    };
  }

  /**
   *
   * @return list<self>
   */
  public static function images(): array {
    return [
      self::IMAGE_JPEG,
      self::IMAGE_PNG,
      self::IMAGE_GIF,
      self::IMAGE_WEBP,
      self::IMAGE_SVG,
      self::IMAGE_BMP
    ];
  }

  /**
   *
   * @return list<self>
   */
  public static function documents(): array {
    return [
      self::PDF,
      self::DOC,
      self::DOCX,
      self::XLS,
      self::XLSX,
      self::PPT,
      self::PPTX,
      self::ODT,
      self::ODS
    ];
  }

  /**
   *
   * @return list<self>
   */
  public static function archives(): array {
    return [
      self::ZIP,
      self::RAR,
      self::GZIP,
      self::TAR,
      self::SEVEN_ZIP
    ];
  }

  /**
   *
   * @return list<self>
   */
  public static function text(): array {
    return [
      self::CSV,
      self::PLAIN_TEXT,
      self::JSON,
      self::XML,
      self::YAML
    ];
  }

  /**
   *
   * @return list<self>
   */
  public static function audio(): array {
    return [
      self::MP3,
      self::WAV,
      self::OGG_AUDIO,
      self::FLAC
    ];
  }

  /**
   *
   * @return list<self>
   */
  public static function video(): array {
    return [
      self::MP4,
      self::WEBM,
      self::AVI,
      self::QUICKTIME
    ];
  }

  /**
   * Constante utilisable dans les attributs PHP.
   */
  public const DEFAULT_VALUES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
    'image/bmp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.oasis.opendocument.spreadsheet',
    'application/zip',
    'application/vnd.rar',
    'application/gzip',
    'application/x-tar',
    'application/x-7z-compressed',
    'text/csv',
    'text/plain',
    'application/json',
    'application/xml',
    'application/x-yaml',
    'audio/mpeg',
    'audio/wav',
    'audio/ogg',
    'audio/flac',
    'video/mp4',
    'video/webm',
    'video/x-msvideo',
    'video/quicktime'
  ];
}