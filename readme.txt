��������� ��������� ���������,����������� � �������������� ������ ���������, ����������� ������ �� ������� �� ������
� ����������� Moodle. ������ ��������� ���� ��������� �� �������� windows, � ��� �� ubuntu �������� Linux ��������.

��� ���������� ������ ��������� ��������� ����������� ������.
���������� ������������ php7 � curl. ��� ������������ ����� ������ ���� ����������� ����� �������, ���:
1. Cmake
2. QT 5.5
3. Make
4. MinGW (���� ������ ������� �� Windows)
5. ��� ���������� �� linux ������ ���� ����������� ��������������� ����������

��� ����, ��� �� ����� ����������� ��������� ��������� �� �����, �������
���������� � ��������� ���� �� ������� �������� �����. ��������, Sendmail.
� ����� conf.ini ���������� ��������� ��������� ��� ������.

������� ������� � ����� � �����������:
username - ����� ������������ �� ������� � ����������� moodle
password - ������ ������������ �� �������
login_url - �������� ��� ����������� � moodle
task_url - ������ �������� � ��������� (��� ���������� id, �������� "http://edu.vstu.org/mod/poasassignment/view.php?id=")
task_id[] - �������, ����������� ��� ��������, ����� ���� ������� ��������� ������� �� ��������� ��������
protocol_url - ������ �������� � ����������� (��� ���������� id, �������� "http://edu.vstu.org/mod/assign/view.php?id=")
protocol_id[] - id ������� � �����������, ����������� ��� ��������, ����� ���� ������� ��������� ������� � �����������
���� ��������� �������� ��������� ��������, ������� ������� ����� send_result_on_email
send_result_on_email   - true, ��� ���� ��� �� ��������� ��� ��������� �� �����
email - �����, ���� ������� ��������� ���������
send_from_email - �����, ������ ������� ���������� ���������

��� ����, ��� �� ������� ������ ������, ������� ������� ����� write_on (�� ��������� ����������� ����� ��� �������)
write_on - ������� ���� �� ��������� ����� "console", "browser", "log"

���� ��������� ���������� ������ �� ������ � ������ ������� ����������, ��������� ���������� �����:
grade_if_fail - ������� ������, ������� ���������� ����������, � �������� �� 0 �� 100

���� ��������� �������� ������ � �����������, ������� ������� ����� write_on_comment
write_on_comment - true, ��� �������� ��������� � �����������

���� ��������� ������� � ��������� ������ ��������� ������� ������� �����
save_answers  - true , ��� ���������� �������
files_download_to - ����� � ������� ����� ��������� ������

���� ��������� ����������� ��������� ����� ������� ������� �����
unpack_answers - true
path_to_winrar - ���� � �������������� ������������ ��� ���������� �������

���� ��������� �������������� ��������� ������ ������� ������� �����
build_and_compile - true

��� �� ���������� ������� ���� � cmake, qmake, make
path_to_CMake 
path_to_QMake 
path_to_Make 

������� ������� ����� ��������� ������ ������������ cmake ��� ���������
generator_for_CMake

����� �������,����� ������� ������ �������� �������, ��������� ��������:cmake, qmake, any. ��� any - ����� �������� ������� ����� ����������.
���� ����� �� ���������, ����� ������ �� ���������, � ������ any.
main_builder

���� ��������� �������������� ������ �� ������, ������� �� ���� ��������������� �����, ������� ������� �����:
check_only_new_work - true

���� ������ ���������� � linux ��������, ���� ��������� �� �������.