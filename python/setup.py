from setuptools import setup, find_packages

with open("README.md", "r", encoding="utf-8") as fh:
    long_description = fh.read()

setup(
    name="mandiri-qris",
    version="1.0.0",
    author="krisadiwijaya",
    author_email="krisadiwijaya@example.com",
    description="Mandiri QRIS Payment Gateway SDK for Python",
    long_description=long_description,
    long_description_content_type="text/markdown",
    url="https://github.com/krisadiwijaya/mandiri-qris-payment-gateway",
    packages=find_packages(),
    classifiers=[
        "Development Status :: 5 - Production/Stable",
        "Intended Audience :: Developers",
        "Topic :: Software Development :: Libraries :: Python Modules",
        "License :: OSI Approved :: MIT License",
        "Programming Language :: Python :: 3",
        "Programming Language :: Python :: 3.7",
        "Programming Language :: Python :: 3.8",
        "Programming Language :: Python :: 3.9",
        "Programming Language :: Python :: 3.10",
        "Programming Language :: Python :: 3.11",
    ],
    python_requires=">=3.7",
    install_requires=[
        "requests>=2.25.0",
    ],
    keywords="mandiri qris payment gateway indonesia",
)
