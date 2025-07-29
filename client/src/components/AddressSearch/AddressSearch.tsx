import React, { useState, useEffect, useRef, useCallback } from 'react';
import styled from 'styled-components';
import { AddressSuggestion } from '../../types';
import { apiService } from '../../services/api';

interface AddressSearchProps {
  value: string;
  onChange: (value: string) => void;
  onCitySelect: (city: string) => void;
  placeholder?: string;
  disabled?: boolean;
}

const Container = styled.div`
  position: relative;
  width: 100%;
`;

const Input = styled.input`
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #e1e5e9;
  border-radius: 8px;
  font-size: 16px;
  transition: border-color 0.2s ease;
  
  &:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
  }
  
  &:disabled {
    background-color: #f5f5f5;
    cursor: not-allowed;
  }
  
  @media (max-width: 768px) {
    padding: 14px 16px;
    font-size: 16px; /* Предотвращает зум на iOS */
  }
`;

const SuggestionsContainer = styled.div`
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #e1e5e9;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
  z-index: 1000;
  max-height: 250px;
  overflow-y: auto;
  margin-top: 4px;
  
  @media (max-width: 768px) {
    max-height: 200px;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.15);
  }
`;

const SuggestionsHeader = styled.div`
  padding: 10px 12px;
  border-bottom: 1px solid #f0f0f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8f9fa;
  position: sticky;
  top: 0;
  
  @media (max-width: 768px) {
    padding: 8px 12px;
  }
`;

const SuggestionsTitle = styled.span`
  font-weight: 600;
  color: #333;
  font-size: 13px;
`;

const SuggestionsCount = styled.span`
  font-size: 11px;
  color: #666;
`;

const SuggestionItem = styled.div<{ highlighted?: boolean }>`
  display: flex;
  align-items: center;
  padding: 12px 14px;
  cursor: pointer;
  transition: background-color 0.15s ease;
  border-bottom: 1px solid #f5f5f5;
  min-height: 44px;
  
  &:hover,
  ${props => props.highlighted && 'background-color: #f8f9fa;'}
  
  &:last-child {
    border-bottom: none;
  }
  
  @media (max-width: 768px) {
    padding: 14px 12px;
    min-height: 48px;
  }
`;

const SuggestionIcon = styled.span`
  font-size: 16px;
  margin-right: 10px;
  opacity: 0.7;
`;

const SuggestionContent = styled.div`
  flex: 1;
`;

const SuggestionTitle = styled.div`
  font-weight: 500;
  color: #333;
  margin-bottom: 2px;
  font-size: 14px;
  
  mark {
    background-color: #fff3cd;
    color: #856404;
    padding: 0 2px;
    border-radius: 2px;
  }
`;

const SuggestionSubtitle = styled.div`
  font-size: 12px;
  color: #666;
`;

const LoadingMessage = styled.div`
  padding: 12px 14px;
  text-align: center;
  color: #666;
  font-size: 14px;
`;

const NoResults = styled.div`
  padding: 12px 14px;
  text-align: center;
  color: #666;
  font-size: 14px;
`;

const AddressSearch: React.FC<AddressSearchProps> = ({
  value,
  onChange,
  onCitySelect,
  placeholder = "Введите город доставки",
  disabled = false
}) => {
  const [suggestions, setSuggestions] = useState<AddressSuggestion[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(-1);
  
  const inputRef = useRef<HTMLInputElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const debounceRef = useRef<NodeJS.Timeout>();

  // Дебаунсированный поиск
  const debouncedSearch = useCallback(async (query: string) => {
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
    }

    debounceRef.current = setTimeout(async () => {
      if (query.length >= 2) {
        setIsLoading(true);
        try {
          const results = await apiService.getAddressSuggestions(query, 10);
          setSuggestions(results);
          setShowSuggestions(true);
        } catch (error) {
          console.error('Ошибка поиска адресов:', error);
          setSuggestions([]);
        } finally {
          setIsLoading(false);
        }
      } else {
        setSuggestions([]);
        setShowSuggestions(false);
      }
    }, 200);
  }, []);

  // Обработка изменения значения
  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newValue = e.target.value;
    onChange(newValue);
    setHighlightedIndex(-1);
    debouncedSearch(newValue);
  };

  // Обработка клавиш
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!showSuggestions || suggestions.length === 0) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setHighlightedIndex(prev => 
          prev < suggestions.length - 1 ? prev + 1 : 0
        );
        break;
      case 'ArrowUp':
        e.preventDefault();
        setHighlightedIndex(prev => 
          prev > 0 ? prev - 1 : suggestions.length - 1
        );
        break;
      case 'Enter':
        e.preventDefault();
        if (highlightedIndex >= 0 && suggestions[highlightedIndex]) {
          selectSuggestion(suggestions[highlightedIndex]);
        }
        break;
      case 'Escape':
        setShowSuggestions(false);
        setHighlightedIndex(-1);
        break;
    }
  };

  // Выбор предложения
  const selectSuggestion = (suggestion: AddressSuggestion) => {
    onChange(suggestion.city);
    onCitySelect(suggestion.city);
    setShowSuggestions(false);
    setHighlightedIndex(-1);
    setSuggestions([]);
  };

  // Подсветка совпадений в тексте
  const highlightMatch = (text: string, query: string) => {
    if (!query) return text;
    
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
  };

  // Закрытие при клике вне компонента
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setShowSuggestions(false);
        setHighlightedIndex(-1);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Очистка таймера при размонтировании
  useEffect(() => {
    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, []);

  return (
    <Container ref={containerRef}>
      <Input
        ref={inputRef}
        type="text"
        value={value}
        onChange={handleInputChange}
        onKeyDown={handleKeyDown}
        placeholder={placeholder}
        disabled={disabled}
        autoComplete="off"
      />
      
      {showSuggestions && (
        <SuggestionsContainer>
          <SuggestionsHeader>
            <SuggestionsTitle>Выберите город</SuggestionsTitle>
            <SuggestionsCount>
              {isLoading ? 'Поиск...' : `${suggestions.length} результатов`}
            </SuggestionsCount>
          </SuggestionsHeader>
          
          {isLoading && (
            <LoadingMessage>🔄 Поиск городов...</LoadingMessage>
          )}
          
          {!isLoading && suggestions.length === 0 && value.length >= 2 && (
            <NoResults>Ничего не найдено. Попробуйте изменить запрос.</NoResults>
          )}
          
          {!isLoading && suggestions.map((suggestion, index) => (
            <SuggestionItem
              key={`${suggestion.city}-${index}`}
              highlighted={index === highlightedIndex}
              onClick={() => selectSuggestion(suggestion)}
            >
              <SuggestionIcon>🏙️</SuggestionIcon>
              <SuggestionContent>
                <SuggestionTitle
                  dangerouslySetInnerHTML={{
                    __html: highlightMatch(suggestion.city, value)
                  }}
                />
                <SuggestionSubtitle>Россия</SuggestionSubtitle>
              </SuggestionContent>
            </SuggestionItem>
          ))}
        </SuggestionsContainer>
      )}
    </Container>
  );
};

export default AddressSearch;